<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

namespace Pages\SkinAPI;

global $sb;

use Actions\UploadActions;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

$actions = new UploadActions($sb);

if (isset($post_data['upload'])) {
    if (isset($post_data['action'])) {
        $upload = (int)$post_data['upload'];

        $error = null;

        switch ($post_data['action']) {
            case 'favorite':
                $apiOutput = [
                    "favorited" => true,
                    "number" => rand(0, 47101),
                ];
                break;
            case 'rate':
                $error = $actions->rate($upload, (int)$post_data['number']);
                $apiOutput = [["rated" => true]];
                break;
            case 'unrate':
                $error = $actions->unrate($upload);
                $apiOutput = [["rated" => false]];
                break;
            case 'like':
                $error = $actions->rate($upload, 5);
                $apiOutput = [["rated" => true]];
                break;
            case 'dislike':
                $error = $actions->rate($upload, 1);
                $apiOutput = [["rated" => true]];
                break;
            default:
                $apiOutput = [
                    "error" => "This interaction type is invalid."
                ];
        }

        if ($error) {
            $apiOutput = [
                "error" => $sb->getLocalizationClass()->translate($error->value)
            ];
        }
    }
}

echo json_encode($apiOutput);
