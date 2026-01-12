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

namespace OpenSB\Pages;

global $sb, $database, $twig, $auth;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;
use OpenSB\UploadFlags;

// TODO: a more automated method to detect which file format the user is trying to upload.
$supportedVideoFormats = ["mp4", "mkv", "wmv", "flv", "avi", "mov", "3gp"];
$supportedImageFormats = ["png", "jpg", "jpeg", "bmp", "webp"];
$supportedAudioFormats = ["mp3", "wav", "flac", "aiff", "ogg", "wma", "m4a"]; // TODO

// tip: if youre hosting opensb on a linux distro with selinux included (eg: fedora) and you get some
// kind of access denied error. run these commands as root/sudo:
// semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/opensb/dynamic(/.*)?"
// restorecon -Rv /var/www/opensb/dynamic
// -chaziz 4/20/2025

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->getUserBanData()) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($sb->isLockdownEnabled()) {
    Utilities::notifyBanner("notify_upload_disabled", "/");
}

if ($auth->getUserFlags(true)["unverified"]) {
    http_response_code(401);
    echo $twig->render('unverified.twig');
    die();
}

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    $joindate = $auth->getUserData()["joined"];
    $timeSinceJoin = time() - strtotime($joindate);

    if ($timeSinceJoin < 2 * 24 * 60 * 60) {
        // if we have a new account, make the ratelimit longer as an antispam measure.
        $rateLimit = 3 * 60;
    } elseif ($timeSinceJoin < 7 * 24 * 60 * 60) {
        // if its 2-7 days old make the rate limit smaller.
        $rateLimit = 2 * 60;
    } else {
        // if it is older than that, keep our usual ratelimit of one minute.
        $rateLimit = 1 * 60;
    }

    if ($database->result("SELECT COUNT(*) FROM uploads WHERE timestamp > ? AND author = ?", [time() - $rateLimit, $auth->getUserID()]) && !$sb->isDebug()) {
        $waitTimeMinutes = $rateLimit / 60;
        Utilities::notifyBanner("notify_upload_ratelimit", "/", "warning", [$waitTimeMinutes]);
    }
}

function parse_tags($tags, $upload_id, $database)
{
    // parse tags from input
    $tagsID = [];
    foreach ($tags as $tag) {
        // remove hashtags from tags.
        $tag = preg_replace('/#(\w+)/', '$1', $tag);

        $tagId = $database->result("SELECT tag_id FROM upload_tag_meta WHERE name = ?", [$tag]);

        if ($tagId === false) {
            $database->query("INSERT INTO upload_tag_meta (name, last_usage) VALUES (?,?)", [$tag, time()]);
            $tagId = $database->insertId(); // Get the ID of the newly inserted tag
        } else {
            $database->query("UPDATE upload_tag_meta SET last_usage = ? WHERE name = ?", [time(), $tag]);
        }

        $tagsID[] = $tagId;
    }

    $upload_integer_id = $database->result("SELECT id from uploads WHERE upload_id = ?", [$upload_id]);

    // link tags to the upload
    foreach ($tagsID as $tagID) {
        if (!$database->result("SELECT tag_id FROM upload_tag_index WHERE tag_id = ? AND upload_id = ?", [$tagID, $upload_integer_id])) {
            $database->query("INSERT INTO upload_tag_index (upload_id, tag_id) VALUES (?,?)", [$upload_integer_id, $tagID]);
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

if (isset($_POST['upload']) or isset($_POST['upload_video']) and $auth->isUserLoggedIn()) {
    $flags = 0;

    $new = Utilities::generateRandomString(11, true);
    $uploader = $auth->getUserID();

    $title = ($_POST['title'] ?? null);
    $description = ($_POST['desc'] ?? null);

    // kinda fucking stupid way to do this but whatever
    $mature = $auth->isUserOver18() && 
            isset($_POST['rating']) && 
            $_POST['rating'];

    if ($mature) {
        $flags |= UploadFlags::FLAG_MATURE->value;
    }

    $tags = ($_POST['tags'] ?? '');
    if ($tags === '') {
        $tags2 = [];
    } else {
        $tags2 = preg_split('/[\s,]+/', trim($tags, ","));
    }

    if ($sb->isDebug()) {
        $noProcess = ($_POST['debugUploaderSkip'] ?? null);
    }

    $name = $_FILES['fileToUpload']['name'];
    $temp_name = $_FILES['fileToUpload']['tmp_name']; // gets upload info
    $ext = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);

    if (in_array(strtolower($ext), $supportedVideoFormats, true)) { // VIDEO
        if (isset($noProcess) && $sb->isDebug()) {
            // pretend video has been successfully uploaded (does this still work???)
            $target_file = SB_DYNAMIC_PATH . '/dynamic/videos/' . $new . '.converted.' . $ext;
        } else {
            $flags |= UploadFlags::FLAG_UNPROCESSED->value;
            $target_file = SB_DYNAMIC_PATH . '/videos/' . $new . '.' . $ext;
        }
        if (move_uploaded_file($temp_name, $target_file)) {
            $database->query(
                "INSERT INTO uploads (upload_id, title, description, author, timestamp, tags, upload_file, flags) VALUES (?,?,?,?,?,?,?,?)",
                [$new, $title, $description, $uploader, time(), json_encode($tags2), 'dynamic/videos/' . $new, $flags]
            );

            if (!isset($noProcess)) {
                $sb->getStorageClass()->processVideoUpload($new, $target_file);
            }

            parse_tags($tags2, $new, $database);

            if ($sb->isDiscordWebhookEnabled()) {
                discord_webhook_notify($sb, $new, $title, $description, $auth);
            }

            Utilities::notifyBanner("notify_upload_success", "/view/" . $new, "success");
        } else {
            if ($sb->isDebug()) {
                die("DEBUG: Unable to move $temp_name to $target_file, check your permissions!");
            } else {
                Utilities::notifyBanner("notify_upload_technical_issue", "/upload");
            }
        }
    } elseif (in_array(strtolower($ext), $supportedImageFormats, true)) { // IMAGES
        try {
        $sb->getStorageClass()->processImageUpload($temp_name, $new);
        } catch (\Exception $e) {
            if ($sb->isDebug()) {
                die("DEBUG: Unable to process image upload. The exception's message is {$e->getMessage()}.");
            } else {
                Utilities::notifyBanner("notify_upload_technical_issue", "/upload");
            }
        }
        
        $database->query(
            "INSERT INTO uploads (upload_id, title, description, author, timestamp, tags, upload_file, flags, type) VALUES (?,?,?,?,?,?,?,?,?)",
            [$new, $title, $description, $uploader, time(), json_encode(explode(', ', $_POST['tags'])), '/dynamic/art/' . $new . '.png', $flags, 2]
        );

        parse_tags($tags2, $new, $database);

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $new, $title, $description, $auth);
        }

        Utilities::notifyBanner("notify_upload_success", "/view/" . $new, "success");
    } elseif (in_array(strtolower($ext), $supportedAudioFormats, true)) { // AUDIO
        Utilities::notifyBanner("notify_upload_audio_unimplemented", "/upload");
    } else {
        Utilities::notifyBanner("notify_invalid_format", "/upload");
    }
}

echo $twig->render('upload.twig', [
    'limit' => (Utilities::formatBytes(ini_get('upload_max_filesize'))),
]);
