<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
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

namespace OpenSB;

global $orange, $database, $twig, $auth;

use SquareBracket\Utilities;
use SquareBracket\UserRoleEnum;
use SquareBracket\DiscordWebhookLogging;

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
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserBanData()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if ($orange->isLockdownEnabled()) {
    Utilities::notifyBanner("The ability to upload has been disabled.", "/");
}

if (!$auth->userHasRole(UserRoleEnum::Administrator)) {
    $joindate = $auth->getUserData()["joined"];
    $timeSinceJoin = time() - strtotime($joindate);

    if ($timeSinceJoin < 2 * 24 * 60 * 60) {
        // if we have a new account, make the ratelimit longer as an antispam measure.
        $rateLimit = 10 * 60;
    } elseif ($timeSinceJoin < 7 * 24 * 60 * 60) {
        // if its 2-7 days old make the rate limit smaller.
        $rateLimit = 5 * 60;
    } else {
        // if it is older than that, keep our usual ratelimit of two minutes.
        $rateLimit = 2 * 60;
    }

    if ($database->result("SELECT COUNT(*) FROM uploads WHERE time > ? AND author = ?", [time() - $rateLimit, $auth->getUserID()]) && !$orange->isDebug()) {
        $waitTimeMinutes = $rateLimit / 60;
        Utilities::notifyBanner("Please wait at least {$waitTimeMinutes} minutes before uploading again.", "/");
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
            $database->query("INSERT INTO upload_tag_meta (name, latestUse) VALUES (?,?)", [$tag, time()]);
            $tagId = $database->insertId(); // Get the ID of the newly inserted tag
        } else {
            $database->query("UPDATE upload_tag_meta SET latestUse = ? WHERE name = ?", [time(), $tag]);
        }

        $tagsID[] = $tagId;
    }

    $upload_integer_id = $database->result("SELECT id from uploads WHERE video_id = ?", [$upload_id]);

    // link tags to the upload
    foreach ($tagsID as $tagID) {
        if (!$database->result("SELECT tag_id FROM upload_tag_index WHERE tag_id = ? AND video_id = ?", [$tagID, $upload_integer_id])) {
            $database->query("INSERT INTO upload_tag_index (video_id, tag_id) VALUES (?,?)", [$upload_integer_id, $tagID]);
        }
    }
}

if (isset($_POST['upload']) or isset($_POST['upload_video']) and $auth->isUserLoggedIn()) {
    $new = Utilities::generateRandomString(11, true);
    $uploader = $auth->getUserID();

    $title = ($_POST['title'] ?? null);
    $description = ($_POST['desc'] ?? null);

    if ($orange->isChazizSquareBracketInstance()) {
        $rating = 'general';
    } else {
        $rating = isset($_POST['rating']) && $_POST['rating'] === 'true' ? 'mature' : 'general';
    }

    $tags = ($_POST['tags'] ?? '');
    if ($tags === '') {
        $tags2 = [];
    } else {
        $tags2 = preg_split('/[\s,]+/', trim($tags, ","));
    }

    if ($orange->isDebug()) {
        $noProcess = ($_POST['debugUploaderSkip'] ?? null);
    }

    $name = $_FILES['fileToUpload']['name'];
    $temp_name = $_FILES['fileToUpload']['tmp_name']; // gets upload info
    $ext = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);

    if (in_array(strtolower($ext), $supportedVideoFormats, true)) { // VIDEO
        if (isset($noProcess) && $orange->isDebug()) {
            $status = 0x0; // pretend that video has been successfully uploaded
            $target_file = BLUFF_DYNAMIC_PATH . '/dynamic/videos/' . $new . '.converted.' . $ext;
        } else {
            $status = 0x2;
            $target_file = BLUFF_DYNAMIC_PATH . '/videos/' . $new . '.' . $ext;
        }
        if (move_uploaded_file($temp_name, $target_file)) {
            $database->query(
                "INSERT INTO uploads (video_id, title, description, author, time, tags, videofile, flags, rating) VALUES (?,?,?,?,?,?,?,?,?)",
                [$new, $title, $description, $uploader, time(), json_encode($tags2), 'dynamic/videos/' . $new, $status, $rating]
            );

            if (!isset($noProcess)) {
                $orange->getStorageClass()->processVideoUpload($new, $target_file);
            }

            parse_tags($tags2, $new, $database);

            if ($orange->isDiscordWebhookEnabled()) {
                $webhookdata = [
                    'video_id' => $new,
                    'name' => $title,
                    'description' => $description,
                    'author' => $auth->getUserData()["name"]
                ];

                $orange->getDiscordWebhookClass()->newUploadHook($webhookdata);
            }

            Utilities::notifyBanner("Your upload has been posted.", "/view/" . $new, "success");
        } else {
            Utilities::notifyBanner("There is a problem with file permissions and/or PHP on this instance.", "/upload");
        }
    } elseif (in_array(strtolower($ext), $supportedImageFormats, true)) { // IMAGES
        $orange->getStorageClass()->processImageUpload($temp_name, $new);
        $status = 0x0;
        $database->query(
            "INSERT INTO uploads (video_id, title, description, author, time, tags, videofile, flags, post_type, rating) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$new, $title, $description, $uploader, time(), json_encode(explode(', ', $_POST['tags'])), '/dynamic/art/' . $new . '.png', $status, 2, $rating]
        );

        parse_tags($tags2, $new, $database);

        Utilities::notifyBanner("Your upload has been posted.", "/view/" . $new, "success");
    } elseif (in_array(strtolower($ext), $supportedAudioFormats, true)) { // MUSIC
        Utilities::notifyBanner("Audio uploading will be implemented at a later date.", "/upload");
    } else {
        Utilities::notifyBanner("This file format is not supported.", "/upload");
    }
}

echo $twig->render('upload.twig', [
    'limit' => (Utilities::convertBytes(ini_get('upload_max_filesize'))),
]);
