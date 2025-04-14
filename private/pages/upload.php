<?php

namespace OpenSB;

global $database, $twig, $disableUploading, $auth, $isDebug, $storage, $isChazizSB;

use SquareBracket\Pages\SubmissionUpload;
use SquareBracket\Templating;
use SquareBracket\Utilities;

// TODO: a more automated method to detect which file format the user is trying to upload.
$supportedVideoFormats = ["mp4", "mkv", "wmv", "flv", "avi", "mov", "3gp"];
$supportedImageFormats = ["png", "jpg", "jpeg", "bmp", "webp"];
$supportedMusicFormats = ["mp3", "wav", "flac", "aiff", "ogg", "wma", "m4a"]; // TODO

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserBanData()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if ($disableUploading) {
    Utilities::notifyBanner("The ability to upload has been disabled.", "/");
}

if (!$auth->isUserAdmin()) {
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

    if ($database->result("SELECT COUNT(*) FROM uploads WHERE time > ? AND author = ?", [time() - $rateLimit, $auth->getUserID()]) && !$isDebug) {
        $waitTimeMinutes = $rateLimit / 60;
        Utilities::notifyBanner("Please wait at least {$waitTimeMinutes} minutes before uploading again.", "/");
    }
}

function parse_tags($tags, $upload_id, $database) {
    // parse tags from input
    $tagsID = [];
    foreach ($tags as $tag) {
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

    if ($isChazizSB) {
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

    if ($isDebug) {
        $noProcess = ($_POST['debugUploaderSkip'] ?? null);
    }

    $name = $_FILES['fileToUpload']['name'];
    $temp_name = $_FILES['fileToUpload']['tmp_name']; // gets upload info
    $ext = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);

    if (in_array(strtolower($ext), $supportedVideoFormats, true)) { // VIDEO
        if (isset($noProcess) && $isDebug) {
            $status = 0x0; // pretend that video has been successfully uploaded
            $target_file = SB_DYNAMIC_PATH . '/dynamic/videos/' . $new . '.converted.' . $ext;
        } else {
            $status = 0x2;
            $target_file = SB_DYNAMIC_PATH . '/videos/' . $new . '.' . $ext;
        }
        if (move_uploaded_file($temp_name, $target_file)) {
            $database->query("INSERT INTO uploads (video_id, title, description, author, time, tags, videofile, flags, rating) VALUES (?,?,?,?,?,?,?,?,?)",
                [$new, $title, $description, $uploader, time(), json_encode($tags2), 'dynamic/videos/' . $new, $status, $rating]);

            if (!isset($noProcess)) {
                $storage->processVideoUpload($new, $target_file);
            }

            parse_tags($tags2, $new, $database);

            Utilities::notifyBanner("Your upload has been completed.", "./watch.php?v=" . $new, "success");
        } else {
            Utilities::notifyBanner("There is a problem with file permissions and/or PHP on this instance.", "/upload");
        }
    } elseif (in_array(strtolower($ext), $supportedImageFormats, true)) { // IMAGES
        $storage->processImageUpload($temp_name, $new);
        $status = 0x0;
        $database->query("INSERT INTO uploads (video_id, title, description, author, time, tags, videofile, flags, post_type, rating) VALUES (?,?,?,?,?,?,?,?,?,?)",
            [$new, $title, $description, $uploader, time(), json_encode(explode(', ', $_POST['tags'])), '/dynamic/art/' . $new . '.png', $status, 2, $rating]);

        parse_tags($tags2, $new, $database);

        Utilities::notifyBanner("Your upload has been completed.", "./watch.php?v=" . $new, "success");
    } elseif (in_array(strtolower($ext), $supportedMusicFormats, true)) { // MUSIC
        Utilities::notifyBanner("Audio uploading will be implemented at a later date.", "/upload");
    } else {
        Utilities::notifyBanner("This file format is not supported.", "/upload");
    }
}

echo $twig->render('upload.twig', [
    'limit' => (Utilities::convertBytes(ini_get('upload_max_filesize'))),
]);;
