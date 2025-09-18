<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $database;

use OpenSB\UploadData;
use OpenSB\UserData;

header('Content-Type: application/json');

$id = ($_GET['id'] ?? null);

$upload = new UploadData($database, $id);

if (!$id) {
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
