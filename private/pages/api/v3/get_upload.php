<?php

namespace OpenSB;

global $database;

use SquareBracket\UploadData;

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

$tags_from_upload = $upload->getTags();

$tags = [];

foreach ($tags_from_upload as $tag) {
    $tags[] = $tag["name"];
}

$apiOutput = [
    'id' => $data['video_id'],
    'title' => $data['title'],
    'description' => $data['description'],
    'uploaded' => $data['time'],
    'views' => $data['views'],
    'file' => $data['videofile'],
    'tags' => $tags,
];

echo json_encode($apiOutput);