<?php

namespace OpenSB;

global $database;

use SquareBracket\UploadQuery;

header('Content-Type: application/json');

$upload_query = new UploadQuery($database);

$submissions_random = $upload_query->query("RAND()", 8);

$apiOutput = [
    'uploads' => $submissions_random,
];

echo json_encode($apiOutput);