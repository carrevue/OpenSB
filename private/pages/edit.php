<?php

namespace OpenSB;

global $twig, $database, $auth, $storage;

use SquareBracket\UploadData;
use SquareBracket\Utilities;

if (isset($_POST['upload'])) {
    $id = ($_POST['vid_id'] ?? null);
} else {
    $id = ($_GET['v'] ?? null);
}

$submission = new UploadData($database, $id);
$data = $submission->getData();

if (!$auth->isUserLoggedIn())
{
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

    if (!empty($_FILES['thumbnail']['name'])) {
        $name = $_FILES['thumbnail']['name'];
        $temp_name = $_FILES['thumbnail']['tmp_name'];
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $storage->processCustomUploadThumbnail($temp_name, $data["video_id"]);
    }

    $database->query("UPDATE uploads SET title = ?, description = ? WHERE video_id = ?",
        [$title, $desc, $id]);
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