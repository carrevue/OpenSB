<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

global $auth, $database;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if ($auth->getUserBanData()) {
    $apiOutput = [
        "error" => "You have been banned."
    ];
}

function rate($number, $submission): array
{
    global $database, $auth;

    // shouldn't this update instead?
    if ($database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND user=?", [$submission, $auth->getUserID()])) {
        $database->query("DELETE FROM upload_ratings WHERE video=? AND user=?", [$submission, $auth->getUserID()]);
    }
    $database->query("INSERT INTO upload_ratings (video, user, rating) VALUES (?,?,?)", [$submission, $auth->getUserID(), $number]);
    return ["rated" => true];
}

if (isset($post_data['submission'])) {
    if (isset($post_data['action'])) {
        $apiOutput = match ($post_data['action']) {
            // favorites are still unimplemented FUCK -chaziz 10/31/2024
            'favorite' => [
                "favorited" => true,
                "number" => rand(0, 47101), // placeholder code (which is still placeholder even a year later since favorites were never implemented WHOOPS)
            ],
            'rate' => [
                rate($post_data['number'], $post_data['submission']),
            ],
            default => [
                "error" => "This interaction type is invalid or has not yet been implemented."
            ],
        };
    }
}

echo json_encode($apiOutput);
