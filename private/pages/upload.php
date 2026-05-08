<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou
  Copyright (C) 2021 Veselcraft

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

namespace Pages;

global $sb, $database, $twig, $auth;

use \Exception;

use Core\Utilities;
use Data\User\UserRoleEnum;
use Data\Upload\UploadFlags;
use Data\Upload\UploadTypeEnum;
use Data\Upload\UploadVisibilityEnum;

// supported extensions
$supportedVideoFormats = ["mp4", "mkv", "wmv", "flv", "avi", "mov", "3gp"];
$supportedImageFormats = ["png", "jpg", "jpeg", "bmp", "webp"];
$supportedAudioFormats = ["mp3", "wav", "flac", "aiff", "ogg", "wma", "m4a"];

// supported mime types
function detectUploadType(string $tmpPath, string $extension, array $videoExts, array $imageExts, array $audioExts): ?string
{
    $ext = strtolower($extension);
    $mimeToType = [
        'video/mp4' => 'video', 'video/x-matroska' => 'video', 'video/x-ms-wmv' => 'video',
        'video/x-flv' => 'video', 'video/x-msvideo' => 'video', 'video/quicktime' => 'video',
        'video/3gpp' => 'video', 'video/webm' => 'video',
        'image/png' => 'image', 'image/jpeg' => 'image', 'image/bmp' => 'image', 'image/webp' => 'image', 'image/gif' => 'image',
        'audio/mpeg' => 'audio', 'audio/wav' => 'audio', 'audio/flac' => 'audio', 'audio/aiff' => 'audio',
        'audio/ogg' => 'audio', 'audio/x-ms-wma' => 'audio', 'audio/mp4' => 'audio', 'audio/x-m4a' => 'audio',
    ];
    if (file_exists($tmpPath)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? @finfo_file($finfo, $tmpPath) : null;

        if ($mime && isset($mimeToType[$mime])) {
            return $mimeToType[$mime];
        }
    }
    if (in_array($ext, $videoExts, true)) {
        return 'video';
    }
    if (in_array($ext, $imageExts, true)) {
        return 'image';
    }
    if (in_array($ext, $audioExts, true)) {
        return 'audio';
    }
    return null;
}

// tip: if youre hosting opensb on a linux distro with selinux included (eg: fedora) and you get some
// kind of access denied error. run these commands as root/sudo:
// semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/opensb/dynamic(/.*)?"
// restorecon -Rv /var/www/opensb/dynamic
// -chaziz 4/20/2025

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->isBanned() || $auth->getUserFlags(true)["unverified"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($sb->isLockdownEnabled()) {
    Utilities::notifyBanner("notify_upload_disabled", "/");
}

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {

    $now = time();
    $userId = $auth->getUserID();
    $joined = strtotime($auth->getUserData()['joined']);
    $age = $now - $joined;

    if ($age < 2 * 86400) {
        $rateLimit = 180;
    } elseif ($age < 7 * 86400) {
        $rateLimit = 120;
    } else {
        $rateLimit = 60;
    }

    if (!$sb->isDebug()) {
        $recentUploads = $database->result(
            "SELECT COUNT(*) FROM uploads WHERE timestamp > ? AND author = ?",
            [$now - $rateLimit, $userId]
        );

        if ($recentUploads) {
            Utilities::notifyBanner(
                "notify_upload_ratelimit",
                "/",
                "warning",
                [$rateLimit / 60]
            );
        }
    }
}

function parse_tags(array $tags, string $upload_id, $database): void
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
    $uploadIntId = $database->result(
        "SELECT id FROM uploads WHERE upload_id = ?",
        [$upload_id]
    );

    foreach ($tags as $tag) {
        $tagId = $database->result(
            "SELECT tag_id FROM upload_tag_meta WHERE name = ?",
            [$tag]
        );

        if (!$tagId) {
            // insert in tag meta
            $database->query(
                "INSERT INTO upload_tag_meta (name, last_usage) VALUES (?, ?)",
                [$tag, $now]
            );
            $tagId = $database->insertId();
        } else {
            // bump last usage
            $database->query(
                "UPDATE upload_tag_meta SET last_usage = ? WHERE tag_id = ?",
                [$now, $tagId]
            );
        }

        $exists = $database->result(
            "SELECT 1 FROM upload_tag_index WHERE tag_id = ? AND upload_id = ?",
            [$tagId, $uploadIntId]
        );

        if (!$exists) {
            $database->query(
                "INSERT INTO upload_tag_index (upload_id, tag_id) VALUES (?, ?)",
                [$uploadIntId, $tagId]
            );
        }
    }
}
function discord_webhook_notify($sb, $new, $title, $description, $auth)
{
    $data = [
        'id' => $new,
        'name' => $title,
        'description' => $description,
        'author' => $auth->getUserData()["name"]
    ];

    $sb->getDiscordWebhookClass()->newUploadHook($data);
}

if (
    (isset($_POST['upload']) || isset($_POST['upload_video'])) &&
    $auth->isUserLoggedIn()
) {
    $new = Utilities::generateRandomString(11, true);
    $uploader = $auth->getUserID();
    $title = $_POST['title'] ?? null;
    $desc = $_POST['desc'] ?? null;
    $visibility = $_POST['visibility'] ?? "public";
    $tagsRaw = $_POST['tags'] ?? '';
    $flags = 0;

    // visibilty
    $visibility_type = match ($visibility) {
        'private' => UploadVisibilityEnum::Private,
        'unlisted' => UploadVisibilityEnum::Unlisted,
        'public' => UploadVisibilityEnum::Public,
        default => UploadVisibilityEnum::Public,
    };

    // mature flag
    if (
        $auth->isUserOver18() &&
        !empty($_POST['rating']) &&
        $sb->isMatureUploadsEnabled()
    ) {
        $flags |= UploadFlags::FLAG_MATURE->value;
    }

    // tags
    $tags = $tagsRaw === ''
        ? []
        : preg_split('/[\s,]+/', trim($tagsRaw, ','));

    // file info
    $file = $_FILES['fileToUpload'];
    $temp = $file['tmp_name'];
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $path = $sb->getStorageClass()->getPath();

    $type = detectUploadType(
        $temp,
        $ext,
        $supportedVideoFormats,
        $supportedImageFormats,
        $supportedAudioFormats
    );

    $uploadFilePath = null;
    $uploadTypeEnum = null;

    try {
        switch ($type) {
            case 'video':
                $uploadTypeEnum = UploadTypeEnum::Video;

                $flags |= UploadFlags::FLAG_UNPROCESSED->value;
                $target = "$path/videos/$new.$ext";

                if (!move_uploaded_file($temp, $target)) {
                    throw new Exception("Failed to move uploaded file.");
                }

                $sb->getStorageClass()->processVideoUpload($new, $target);

                $uploadFilePath = "dynamic/videos/$new";
                break;

            case 'image':
                $uploadTypeEnum = UploadTypeEnum::Image;

                $sb->getStorageClass()->processImageUpload($temp, $new);

                $uploadFilePath = "/dynamic/art/$new.png";
                break;

            case 'audio':
                Utilities::notifyBanner("notify_upload_audio_unimplemented", "/upload");
                return;

            default:
                Utilities::notifyBanner("notify_invalid_format", "/upload");
                return;
        }

        $database->query(
            "INSERT INTO uploads 
            (upload_id, title, description, author, timestamp, tags, upload_file, flags, type, visibility)
            VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $new,
                $title,
                $desc,
                $uploader,
                time(),
                json_encode($tags),
                $uploadFilePath,
                $flags,
                $uploadTypeEnum->value,
                $visibility_type->value,
            ]
        );

        parse_tags($tags, $new, $database);

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $new, $title, $desc, $auth);
        }

        $auth->bumpLastActive();

        $database->query("UPDATE users SET u_index = ? WHERE id = ?", [$auth->getUserData()["u_index"] + 1, $member]);
        $database->query("INSERT INTO upload_number_history (upload, date, views, views_raw) VALUES (?,?,?,?)", [$new, date('Y-m-d'), 0, 0]);
        
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
        ];

        if ($sb->isChazizInstance()) {
            $is_suspected_reupload = false;
            foreach ($reupload_suspect_title as $phrase) {
                if (str_contains(strtolower($title), strtolower($phrase))) {
                    $is_suspected_reupload = true;
                    break;
                }
            }
            foreach ($reupload_suspect_description as $phrase) {
                if (str_contains(strtolower($desc), strtolower($phrase))) {
                    $is_suspected_reupload = true;
                    break;
                }
            }

            if ($is_suspected_reupload) {
                Utilities::notifyBanner("notify_upload_success_suspected_reupload", "/view/$new", "warning");
            } else {
                Utilities::notifyBanner("notify_upload_success", "/view/$new", "success");
            }
        } else {
            Utilities::notifyBanner("notify_upload_success", "/view/$new", "success");
        }
    } catch (Exception $e) {
        if ($sb->isDebug()) {
            die("DEBUG: " . $e->getMessage());
        }

        Utilities::notifyBanner("notify_upload_technical_issue", "/upload");
    }
}

echo $twig->render('upload.twig', [
    'limit' => (Utilities::formatBytes(ini_get('upload_max_filesize'))),
]);
