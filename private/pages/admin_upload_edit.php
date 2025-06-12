<?php

namespace OpenSB;

global $auth, $twig, $database, $orange, $path;

use SquareBracket\UploadData;
use SquareBracket\UploadFlags;
use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

$id = $path[3] ?? null;

$upload = new UploadData($database, $id);

$data = $upload->getData();

if (!$data)
{
    Utilities::notifyBanner("This upload does not exist.", "/admin/");
}

$flags = $upload->getUploadFlagsArray();

// Update flags
if (isset($_POST['flagsubmit'])) {
    $flags = 0;

    if (isset($_POST['flag_featured'])) {
        $flags |= UploadFlags::FLAG_FEATURED->value;
    }
    if (isset($_POST['flag_unprocessed'])) {
        $flags |= UploadFlags::FLAG_UNPROCESSED->value;
    }
    if (isset($_POST['flag_block_guests'])) {
        $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
    }
    if (isset($_POST['flag_block_comments'])) {
        $flags |= UploadFlags::FLAG_BLOCK_COMMENTS->value;
    }
    if (isset($_POST['flag_custom_thumbnail'])) {
        $flags |= UploadFlags::FLAG_CUSTOM_THUMBNAIL->value;
    }

    $database->query("UPDATE uploads SET flags = ? WHERE video_id = ?",
        [$flags, $id]);
    Utilities::notifyBanner("Your upload's details have been successfully modified.",
        "/admin/uploads/" . $id, "success");
}


if (file_exists(SB_DYNAMIC_PATH . "/videos/" . $id . ".log")) {
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
    "file" => $data["videofile"],
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
    "flags" => $flags,
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