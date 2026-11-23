<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

  OpenSB is free software: you can redistribute it and/or modify it under the 
  terms of the GNU Affero General Public License as published by the Free 
  Software Foundation, either version 3 of the License, or (at your option) any
  later version. 

  OpenSB is distributed in the hope that it will be useful, but WITHOUT ANY 
  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
  FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more 
  details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace Actions;

use Core\Utilities;
use Data\Upload\UploadData;
use Data\Upload\UploadFlags;
use Data\Upload\UploadTypeEnum;
use Data\Upload\UploadVisibilityEnum;
use Data\User\UserRoleEnum;
use Exception;

class UploadActions extends BaseActions
{
    public function checkOwner(string|int $upload, bool $allowTakenDown = false): UploadData|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        if ($upload->getData()["author"] != $user) {
            return ActionError::NoPermission;
        }

        if (!$allowTakenDown && $upload->isTakenDown()) {
            return ActionError::NoPermission;
        }

        return $upload;
    }

    public function uploadRateLimit(): int
    {
        $age = time() - (int)($this->auth->getUserData()['joined'] ?? 0);

        if ($age < 2 * 86400) {
            return 180;
        } elseif ($age < 7 * 86400) {
            return 120;
        }

        return 60;
    }

    public function canUpload(): int|ActionError
    {
        $user = $this->requireVerified();

        if ($user instanceof ActionError) {
            return $user;
        }

        if ($this->sb->isLockdownEnabled()) {
            return ActionError::UploadDisabled;
        }

        if (!$this->auth->userHasRole(UserRoleEnum::Moderator) && !$this->sb->isDebug()) {
            $recentUploads = $this->database->result(
                "SELECT COUNT(*) FROM uploads WHERE timestamp > ? AND author = ?",
                [time() - $this->uploadRateLimit(), $user]
            );

            if ($recentUploads) {
                return ActionError::UploadRatelimit;
            }
        }

        return $user;
    }

    public function create(
        string $temp,
        string $fileName,
        ?string $title,
        ?string $description,
        UploadVisibilityEnum $visibility,
        array $tags = [],
        bool $mature = false,
        ?string $originalSite = null,
        ?int $originalTime = null
    ): string|ActionError {
        $uploader = $this->canUpload();

        if ($uploader instanceof ActionError) {
            return $uploader;
        }

        $this->auth->bumpLastActive();

        $new = Utilities::generateRandomString(11, true);
        $flags = 0;
        $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($fileName, PATHINFO_EXTENSION))) ?: 'bin';
        $path = $this->sb->getStorageClass()->getPath();

        if (!$this->auth->hasUserAuthenticatedAsStaff()) {
            $originalSite = null;
            $originalTime = 0;
        }

        if ($mature && $this->auth->isUserOver18() && $this->sb->isMatureUploadsEnabled()) {
            $flags |= UploadFlags::FLAG_MATURE->value;
        }

        $tags = array_values(array_filter($tags, fn($tag) => $tag !== ''));
        $type = $this->detectUploadType($temp, $ext);

        switch ($type) {
            case 'video':
                $uploadTypeEnum = UploadTypeEnum::Video;

                $flags |= UploadFlags::FLAG_UNPROCESSED->value;
                $target = "$path/videos/$new.$ext";

                $uploadFilePath = "dynamic/videos/$new";
                break;

            case 'image':
                $uploadTypeEnum = UploadTypeEnum::Image;

                $uploadFilePath = "/dynamic/art/$new.png";
                break;

            case 'audio':
                return ActionError::AudioUnimplemented;

            default:
                return ActionError::InvalidFormat;
        }

        $this->database->query(
            "INSERT INTO uploads 
            (upload_id, title, description, author, timestamp, original_site, original_timestamp, tags, upload_file, flags, type, visibility)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $new,
                $title,
                $description,
                $uploader,
                time(),
                $originalSite,
                $originalTime,
                json_encode($tags),
                $uploadFilePath,
                $flags,
                $uploadTypeEnum->value,
                $visibility->value,
            ]
        );

        $this->parseTags($tags, $new);

        if ($this->sb->isWebhookLoggerEnabled()) {
            $this->sb->getWebhookLoggerClass()->newUploadHook([
                'id' => $new,
                'name' => $title,
                'description' => $description,
                'author' => $this->auth->getUserData()["name"]
            ]);
        }

        $this->database->query("UPDATE users SET u_index = u_index + 1 WHERE id = ?", [$uploader]);
        $this->database->query("INSERT INTO upload_number_history (upload, date, views, views_raw) VALUES (?,?,?,?)", [$new, date('Y-m-d'), 0, 0]);

        // move this here to avoid processing videos that failed to be inserted in the db
        // as apparantly this is an issue that can happen. -chaziz 05/16/2026 
        switch ($type) {
            case 'video':
                if (!move_uploaded_file($temp, $target)) {
                    throw new Exception("Failed to move uploaded file.");
                }

                $this->sb->getStorageClass()->processVideoUpload($new, $target);
                break;

            case 'image':
                $this->sb->getStorageClass()->processImageUpload($temp, $new);
                break;
        }

        return $new;
    }

    public function isPossiblySuspectedReupload(?string $title, ?string $description): bool
    {
        if (!$this->sb->isChazizInstance()) {
            return false;
        }

        // ugh. -chaziz 03/04/2026
        $reupload_suspect_title = [
            "nickelodeon",
            "nicktoons",
            "nicktoon",
            "nickjr",
            "nick jr",
            "noggin",
            "disney channel",
            "playhouse disney",
            "disney junior",
            "disney jr",
            "disneyjr",
            "cartoon network",
            "cartoonito",
            "20th century fox",
            "20th century studios",
            "twentieth century fox",
            "reupload",
        ];

        $reupload_suspect_description = [
            "Copyright Disclaimer under section 107",
            "Fair use is a use permitted by copyright statute",
            "No copyright infringement intended",
            "All rights belong to",
            "Credit goes to",
            "Credits to",
            "Credit to",
            "Found this",
            "my fyp",
            "I do not own",
            "Not my video",
            "://youtube.com",
            "://www.youtube.com",
            "://youtu.be"
        ];

        foreach ($reupload_suspect_title as $phrase) {
            if (str_contains(strtolower($title ?? ''), strtolower($phrase))) {
                return true;
            }
        }

        foreach ($reupload_suspect_description as $phrase) {
            if (str_contains(strtolower($description ?? ''), strtolower($phrase))) {
                return true;
            }
        }

        return false;
    }

    public function edit(
        string $upload,
        ?string $title,
        ?string $description,
        UploadVisibilityEnum $visibility,
        bool $blockGuests,
        bool $blockComments,
        ?string $thumbnail = null
    ): ?ActionError {
        $upload = $this->checkOwner($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        $data = $upload->getData();
        $flags = $data["flags"];

        if ($blockGuests) {
            $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
        } else {
            $flags &= ~UploadFlags::FLAG_BLOCK_GUESTS->value;
        }

        if ($blockComments) {
            $flags |= UploadFlags::FLAG_BLOCK_COMMENTS->value;
        } else {
            $flags &= ~UploadFlags::FLAG_BLOCK_COMMENTS->value;
        }

        if ($thumbnail) {
            $this->sb->getStorageClass()->processCustomUploadThumbnail($thumbnail, $data["upload_id"]);

            $flags |= UploadFlags::FLAG_CUSTOM_THUMBNAIL->value;
        }

        $this->database->query(
            "UPDATE uploads SET title = ?, description = ?, flags = ?, visibility = ? WHERE id = ?",
            [$title, $description, $flags, $visibility->value, $data["id"]]
        );

        return null;
    }

    public function delete(string $upload): ?ActionError
    {
        $upload = $this->checkOwner($upload, true);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        $data = $upload->getData();
        $id = $data["upload_id"];

        $this->database->query("INSERT INTO upload_deleted (id, uploaded_time, deleted_time) VALUES (?,?,?)", [$id, $data["timestamp"], time()]);
        $this->database->query("DELETE FROM uploads WHERE upload_id = ?", [$id]);

        $this->auth->bumpLastActive();
        $this->database->query("UPDATE users SET u_index = GREATEST(u_index, 1) - 1 WHERE id = ?", [$data["author"]]);

        $this->sb->getStorageClass()->deleteUploadFile($data);

        if (!$this->database->result("SELECT upload FROM upload_number_history WHERE upload = ? AND date = ?", [$id, date('Y-m-d')])) {
            $this->database->query("INSERT INTO upload_number_history (upload, date, views, views_raw) VALUES (?,?,?,?)", [$id, date('Y-m-d'), 0, 0]);
        } else {
            $this->database->query("UPDATE upload_number_history set views = ?, views_raw = ? WHERE upload = ? AND date = ?", [0, 0, $id, date('Y-m-d')]);
        }

        return null;
    }

    public function rate(string|int $upload, int $rating): ?ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        if ($rating < 1 || $rating > 5) {
            return ActionError::InvalidRating;
        }

        $id = $upload->getData()["id"];

        // shouldn't this update instead?
        $this->database->query("DELETE FROM upload_ratings WHERE upload=? AND user=?", [$id, $user]);
        $this->database->query("INSERT INTO upload_ratings (upload, user, rating) VALUES (?,?,?)", [$id, $user, $rating]);

        return null;
    }

    public function unrate(string|int $upload): ?ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        $this->database->query("DELETE FROM upload_ratings WHERE upload=? AND user=?", [$upload->getData()["id"], $user]);

        return null;
    }

    public function takedown(string $upload, ?string $reason = null): ?ActionError
    {
        $user = $this->requireModerator();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        if ($upload->isTakenDown()) {
            return ActionError::AlreadyTakenDown;
        }

        $data = $upload->getData();
        $reason = $reason ?: "No reason provided.";

        $this->database->query("
            INSERT INTO upload_takedowns (upload, time, reason, sender)
            VALUES (?,?,?,?);
        ", [$data["upload_id"], time(), $reason, $user]);

        // ok this shit is very fucking stupid i do not know why is it like this
        $this->dashboardWebhook($data, "takedown", $reason);

        return null;
    }

    public function restore(string $upload): ?ActionError
    {
        $user = $this->requireModerator();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        if (!$upload->isTakenDown()) {
            return ActionError::RestoreFailed;
        }

        $data = $upload->getData();
        $reason = $upload->getTakedownData()[0]["reason"] ?? "No reason provided.";

        $this->database->query("
            DELETE FROM upload_takedowns
            WHERE upload = ?
        ", [$data["upload_id"]]);

        $this->dashboardWebhook($data, "restore", $reason);

        return null;
    }

    public function setFlags(string $upload, int $flags): ?ActionError
    {
        $user = $this->requireModerator();

        if ($user instanceof ActionError) {
            return $user;
        }

        $upload = $this->find($upload);

        if ($upload instanceof ActionError) {
            return $upload;
        }

        $this->database->query(
            "UPDATE uploads SET flags = ? WHERE id = ?",
            [$flags, $upload->getData()["id"]]
        );

        return null;
    }

    private function detectUploadType(string $tmpPath, string $extension): ?string
    {
        $ext = strtolower($extension);

        $extToType = [
            'mp4' => 'video', 'mkv' => 'video', 'wmv' => 'video', 'flv' => 'video',
            'avi' => 'video', 'mov' => 'video', '3gp' => 'video', 'm4v' => 'video',
            'png' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'bmp' => 'image', 'webp' => 'image',
            'mp3' => 'audio', 'wav' => 'audio', 'flac' => 'audio', 'aiff' => 'audio',
            'ogg' => 'audio', 'wma' => 'audio', 'm4a' => 'audio',
        ];

        $ambiguousMimes = ['audio/mp4', 'audio/x-m4a', 'video/mp4'];

        $mimeToType = [
            'video/quicktime' => 'video', 'video/x-matroska' => 'video', 'video/x-ms-wmv' => 'video',
            'video/x-flv'     => 'video', 'video/x-msvideo'  => 'video', 'video/3gpp'     => 'video',
            'video/webm'      => 'video', 'image/png'        => 'image', 'image/jpeg'     => 'image',
            'image/bmp'       => 'image', 'image/webp'       => 'image', 'image/gif'      => 'image',
            'audio/mpeg'      => 'audio', 'audio/wav'        => 'audio', 'audio/flac'     => 'audio',
            'audio/aiff'      => 'audio', 'audio/ogg'        => 'audio', 'audio/x-ms-wma' => 'audio',
        ];

        if (file_exists($tmpPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmpPath) : null;

            if ($mime && !in_array($mime, $ambiguousMimes, true) && isset($mimeToType[$mime])) {
                return $mimeToType[$mime];
            }
        }

        return $extToType[$ext] ?? null;
    }

    private function parseTags(array $tags, string $upload_id): void
    {
        if (empty($tags)) {
            return;
        }

        $now = time();

        $tags = array_unique(array_filter(array_map(function ($tag) {
            $tag = preg_replace('/#(\w+)/', '$1', $tag);
            return trim($tag);
        }, $tags)));

        if (empty($tags)) {
            return;
        }

        // Get upload integer ID once
        $uploadIntId = $this->database->result(
            "SELECT id FROM uploads WHERE upload_id = ?",
            [$upload_id]
        );

        foreach ($tags as $tag) {
            $tagId = $this->database->result(
                "SELECT tag_id FROM upload_tag_meta WHERE name = ?",
                [$tag]
            );

            if (!$tagId) {
                // insert in tag meta
                $this->database->query(
                    "INSERT INTO upload_tag_meta (name, last_usage) VALUES (?, ?)",
                    [$tag, $now]
                );
                $tagId = $this->database->insertId();
            } else {
                // bump last usage
                $this->database->query(
                    "UPDATE upload_tag_meta SET last_usage = ? WHERE tag_id = ?",
                    [$now, $tagId]
                );
            }

            $exists = $this->database->result(
                "SELECT 1 FROM upload_tag_index WHERE tag_id = ? AND upload_id = ?",
                [$tagId, $uploadIntId]
            );

            if (!$exists) {
                $this->database->query(
                    "INSERT INTO upload_tag_index (upload_id, tag_id) VALUES (?, ?)",
                    [$uploadIntId, $tagId]
                );
            }
        }
    }

    private function find(string|int $upload): UploadData|ActionError
    {
        $upload = new UploadData($this->database, $upload);
        $data = $upload->getData();

        if (!$data || !$data["id"]) {
            return ActionError::InvalidUpload;
        }

        return $upload;
    }

    private function dashboardWebhook(array $data, string $action, string $reason): void
    {
        if (!$this->sb->isWebhookLoggerEnabled()) {
            return;
        }

        $this->sb->getWebhookLoggerClass()->dashboardUploadHook([
            'title' => $data["title"] . " (" . $data["upload_id"] . ")",
            'author' => $this->auth->getUserData()["name"],
            'reason' => $reason,
            'action' => $action,
        ]);
    }
}
