<?php

namespace OpenSB;

global $auth, $twig, $database, $orange, $path, $storage;

use SquareBracket\UploadData;
use SquareBracket\Utilities;
use SquareBracket\UserData;

if (!$auth->isUserAdmin()) {
    Utilities::bannerNotification("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::bannerNotification("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::bannerNotification("Please change your skin to Biscuit.", "/theme");
}

$id = $path[3] ?? null;

$upload = new UploadData($database, $id);

$data = $upload->getData();

if (!$data)
{
    Utilities::bannerNotification("This upload does not exist.", "/admin/");
}

$bools = $upload->bitmaskToArray();

// Update flags
if (isset($_POST['flagsubmit'])) {
    $flags = 0;

    if (isset($_POST['flag_featured'])) {
        $flags |= UploadData::FLAG_FEATURED;
    }
    if (isset($_POST['flag_unprocessed'])) {
        $flags |= UploadData::FLAG_UNPROCESSED;
    }
    if (isset($_POST['flag_block_guests'])) {
        $flags |= UploadData::FLAG_BLOCK_GUESTS;
    }
    if (isset($_POST['flag_block_comments'])) {
        $flags |= UploadData::FLAG_BLOCK_COMMENTS;
    }
    if (isset($_POST['flag_custom_thumbnail'])) {
        $flags |= UploadData::FLAG_CUSTOM_THUMBNAIL;
    }

    $database->query("UPDATE uploads SET flags = ? WHERE video_id = ?",
        [$flags, $id]);
    Utilities::bannerNotification("Your upload's details have been successfully modified.",
        "/admin/uploads/" . $id, "success");
}


if ($storage->fileExists(SB_DYNAMIC_PATH . "/videos/" . $id . ".log")) {
    $log = file_get_contents(SB_DYNAMIC_PATH . "/videos/" . $id . ".log");
} else {
    $log = null;
}

$page_data = [
    "int_id" => $data["id"],
    "id" => $data["video_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["time"],
    "original_site" => $data["original_site"],
    "published_originally" => $data["original_time"],
    "type" => $data["post_type"],
    "file" => Utilities::getUploadFile($data),
    //"author" => [
    //    "id" => $data["author"],
    //    "info" => $author->getUserArray(),
    //    "followers" => $followers,
    //    "following" => $followed,
    //],
    "interactions" => [
        "views" => $data["views"],
        //"ratings" => Utilities::calculateUploadRatings($ratings),
        //"favorites" => $favorites,
        //"comments" => $comment_count,
    ],
    //"comments" => $comment_data,
    "bools" => $bools,
    "rating" => $data["rating"],
    //"recommended" => $recommended_upload_array,
    //"other_by_author" => $uploads_by_author_array,
    //"random" => $random_uploads_array,
    //"tags" => $tags,
    "log" => $log,
];

echo $twig->render('admin_upload_edit.twig', [
    'upload' => $page_data,
]);