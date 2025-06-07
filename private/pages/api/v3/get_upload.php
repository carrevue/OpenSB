<?php

namespace OpenSB;

global $database;

use SquareBracket\UploadData;
use SquareBracket\UserData;

header('Content-Type: application/json');

$id = ($_GET['id'] ?? null);

$upload = new UploadData($database, $id);

if(!$id) {
    $apiOutput = ['error' => "Missing upload ID."];

    echo json_encode($apiOutput);
    die();
}

if ($upload->getTakedown()) {
    $apiOutput = ['error' => "Upload taken down."];
}

if ($upload->isDeleted()) {
    $apiOutput = ['error' => "Upload deleted."];
}

$data = $upload->getData();
if (!$data) {
    $apiOutput = ['error' => "Upload does not exist."];
}

$author = new UserData($database, $data["author"]);

if ($author->isUserBanned()) {
    $apiOutput = ['error' => "Upload taken down."];
}

$tags_from_upload = $upload->getTags();

$tags = [];

foreach ($tags_from_upload as $tag) {
    $tags[] = $tag["name"];
}

// shitty hack
if ($data['post_type'] == 0) {
    $data['videofile'] = $data['videofile'] . ".converted.mp4";
}

$apiOutput = [
    'id' => $data['video_id'],
    'title' => $data['title'],
    'description' => $data['description'],
    'author' => $author->getUserArray(),
    'uploaded' => $data['time'],
    'views' => $data['views'],
    'file' => $data['videofile'],
    'type' => $data['post_type'],
    'tags' => $tags,
];

echo json_encode($apiOutput);