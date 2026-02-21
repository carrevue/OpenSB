<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace OpenSB\Pages\SkinAPI;

global $database;

use OpenSB\Utilities;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if (isset($post_data['username'])) {
    $validate = Utilities::validateUsername($post_data['username'], $database);

    if ($validate) {
        $apiOutput = [
            "code" => 1,
            "message" => $validate,
        ];
    } else {
        $apiOutput = [
            "code" => 0,
            "message" => "Username is valid.",
        ];
    }
}

echo json_encode($apiOutput);