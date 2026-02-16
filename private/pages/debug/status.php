<?php

namespace OpenSB\Pages\Debug;

global $sb, $database, $twig, $auth;

$storage = $sb->getStorageClass();

header('Content-Type: application/json');

$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// Validate JSON
if (!is_array($data) || empty($data['upload_id'])) {
    echo json_encode(["uploaded" => []]);
    exit;
}

$uploadId = preg_replace('/[^a-f0-9\-]/', '', (string)$data['upload_id']);

if ($uploadId === '') {
    echo json_encode(["uploaded" => []]);
    exit;
}

$uploadChunkFolder = SB_PRIVATE_PATH . "/upload_chunks/$uploadId";

$response = ["uploaded" => []];

if (is_dir($uploadChunkFolder)) {
    foreach (glob("$uploadChunkFolder/*.part") as $file) {
        $response["uploaded"][] = (int)basename($file, ".part");
    }
}

echo json_encode($response);