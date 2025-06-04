<?php

namespace OpenSB;

global $orange;

if (!$orange->isDebug()) {
    http_response_code(403);
    die();
}

// lazy as fuck so im just gonna copy in the code from bluffingo.net
//$files_directory = dirname(__DIR__) . '/dynamic/';
$files_directory = SB_PRIVATE_PATH . '/pages/debug/';

$files = glob($files_directory . "*");

$fileUrls = [];
foreach ($files as $file) {
    $fileUrl = basename($file);
    $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);

    $fileUrls[] = ['filename' => $fileUrl];
}
?>
<img src="/assets/chaz_opensb.png" width="200">
<h1>OpenSB Debug</h1>
<hr>
<?php
foreach ($fileUrls as $file) {
    echo sprintf('<a href="/debug/%s">%s</a><br>', $file["filename"], $file["filename"]);
}