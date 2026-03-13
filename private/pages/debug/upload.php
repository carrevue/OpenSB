<?php

// uhh temporary, this will be merged into /upload.php soon

namespace Pages\Debug;

global $sb, $auth;

if (!$auth->isUserLoggedIn()) {
    http_response_code(403);
    exit("You are either not logged in, or you were logged in. Please log in again.");
}

$storage = $sb->getStorageClass();

$maxFinalSize = 1000 * 1024 * 1024; // 1 gigabyte (for now)
$uploadChunkFolder = SB_PRIVATE_PATH . "/upload_chunks";
$uploadTempFolder = SB_PRIVATE_PATH . "/upload_temp";

$uploadId = preg_replace('/[^a-f0-9\-]/', '', $_POST['upload_id'] ?? '');
$index = (int)($_POST['index'] ?? 0);
$total = (int)($_POST['total'] ?? 0);
$originalName = $_POST['filename'] ?? 'file';
$expectedSize = (int)($_POST['filesize'] ?? 0);

if (!$uploadId || !$total || !isset($_FILES['chunk'])) {
    http_response_code(400);
    exit("Invalid request");
}

if ($expectedSize > $maxFinalSize) {
    http_response_code(413);
    exit("File too large");
}

$chunkDir = "$uploadChunkFolder/$uploadId";

if (!is_dir($chunkDir)) {
    mkdir($chunkDir, 0755, true);
}

$chunkPath = "$chunkDir/$index.part";

if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkPath)) {
    http_response_code(500);
    exit("Chunk failed");
}

// check the file size
$currentSize = 0;
foreach (glob("$chunkDir/*.part") as $file) {
    $currentSize += filesize($file);
}

if ($currentSize > $maxFinalSize) {
    array_map('unlink', glob("$chunkDir/*.part"));
    rmdir($chunkDir);
    http_response_code(413);
    exit("Too big");
}

// now that its complete...
$parts = glob("$chunkDir/*.part");
if (count($parts) === $total) {

    // safe filename
    $safeName = bin2hex(random_bytes(16)) . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalName);
    $finalPath = "$uploadTempFolder/$safeName";

    $out = fopen($finalPath, "wb");

    for ($i = 0; $i < $total; $i++) {
        $in = fopen("$chunkDir/$i.part", "rb");
        stream_copy_to_stream($in, $out);
        fclose($in);
    }

    fclose($out);

    array_map('unlink', $parts);
    rmdir($chunkDir);
}

http_response_code(200);