<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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

namespace OpenSB\Pages\Debug;

global $twig, $sb, $database;

use OpenSB\UploadQuery;
use OpenSB\Utilities;

$upload_query = new UploadQuery($sb);
$uploads = $upload_query->query("v.id DESC", 2);

if ($sb->getLocalOptions()["skin"] === "finalium") {
    $data["button_color_types"] = [
        "primary", "default", "destructive", "dark", "light",
    ];

    $data["banner_color_types"] = [
        "error", "warning", "success", "info" 
    ];
} else {
    $data["color_types"] = [
        "accent", "secondary", "success", "danger", "warning",
    ];
}

$iconPattern = SB_PRIVATE_PATH . '/icons/icons/*.svg';
$icons = [];

foreach (glob($iconPattern) as $filePath) {
    if (is_file($filePath)) {
        $data["icons"][] = pathinfo($filePath, PATHINFO_FILENAME);
    }
}

$data["uploads"] = Utilities::makeUploadArray($database, $uploads);

echo $twig->render('design_test.twig', $data);
