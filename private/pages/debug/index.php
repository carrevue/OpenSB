<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace OpenSB;

global $orange;

if (!$orange->isDebug()) {
    http_response_code(403);
    die();
}

// lazy as fuck so im just gonna copy in the code from bluffingo.net
//$files_directory = dirname(__DIR__) . '/dynamic/';
$files_directory = BLUFF_PRIVATE_PATH . '/pages/debug/';

$files = glob($files_directory . "*");

$fileUrls = [];
foreach ($files as $file) {
    $fileUrl = basename($file);
    $fileExtension = pathinfo($fileUrl, PATHINFO_EXTENSION);

    $fileUrls[] = ['filename' => $fileUrl];
}
?>
<img src="/assets/chaz_opensb.png" width="200">
<h1>OpenSB Backend Debug</h1>
<hr>
<?php
foreach ($fileUrls as $file) {
    echo sprintf('<a href="/debug/%s">%s</a><br>', $file["filename"], $file["filename"]);
}
