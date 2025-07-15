<?php

namespace OpenSB;

global $twig, $database, $auth, $orange;

use SquareBracket\UploadData;
use SquareBracket\UploadFlags;
use SquareBracket\Utilities;

if (isset($_POST['upload'])) {
    $id = ($_POST['vid_id'] ?? null);
} else {
    $id = ($_GET['v'] ?? null);
}

$submission = new UploadData($database, $id);
$data = $submission->getData();

$flags = $data["flags"];

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserBanData() || $submission->getTakedown()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if ($auth->getUserID() != $data["author"]) {
    Utilities::notifyBanner("This is not your upload.", "/");
}

if (isset($_POST['upload'])) {
    $title = $_POST['title'] ?? null;
    $desc = $_POST['desc'] ?? null;

    $block_guests = $_POST['block_guests'] ?? false;
    $block_comments = $_POST['block_comments'] ?? false;

    if ($block_guests) {
        $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
    }

    if ($block_guests) {
        $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
    } else {
        $flags &= ~UploadFlags::FLAG_BLOCK_GUESTS->value;
    }

    if ($block_comments) {
        $flags |= UploadFlags::FLAG_BLOCK_COMMENTS->value;
    } else {
        $flags &= ~UploadFlags::FLAG_BLOCK_COMMENTS->value;
    }

    if (!empty($_FILES['thumbnail']['name'])) {
        $name = $_FILES['thumbnail']['name'];
        $temp_name = $_FILES['thumbnail']['tmp_name'];
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $orange->getStorageClass()->processCustomUploadThumbnail($temp_name, $data["video_id"]);

        $flags |= UploadFlags::FLAG_CUSTOM_THUMBNAIL->value;
    }

    $database->query(
        "UPDATE uploads SET title = ?, description = ?, flags = ? WHERE video_id = ?",
        [$title, $desc, $flags, $id]
    );
    Utilities::notifyBanner("Your upload's details have been successfully modified.", "/view/" . $id, "success");
}

$infoData = [
    "int_id" => $data["id"],
    "id" => $data["video_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["time"],
    "type" => $data["post_type"],
];

echo $twig->render('edit.twig', [
    'data' => $infoData,
]);
