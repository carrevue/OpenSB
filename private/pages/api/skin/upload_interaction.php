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

namespace OpenSB\Pages\FrontendAPI;

global $auth, $database;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if ($auth->isBanned()) {
    $apiOutput = [
        "error" => "You have been banned."
    ];
}

function rate($number, $upload): array
{
    global $database, $auth;

    // shouldn't this update instead?
    if ($database->result("SELECT COUNT(rating) FROM upload_ratings WHERE upload=? AND user=?", [$upload, $auth->getUserID()])) {
        $database->query("DELETE FROM upload_ratings WHERE upload=? AND user=?", [$upload, $auth->getUserID()]);
    }
    $database->query("INSERT INTO upload_ratings (upload, user, rating) VALUES (?,?,?)", [$upload, $auth->getUserID(), $number]);
    return ["rated" => true];
}

function unrate($upload): array
{
    global $database, $auth;

    if ($database->result("SELECT COUNT(rating) FROM upload_ratings WHERE upload=? AND user=?", [$upload, $auth->getUserID()])) {
        $database->query("DELETE FROM upload_ratings WHERE upload=? AND user=?", [$upload, $auth->getUserID()]);
    }
    return ["rated" => false];
}

if (isset($post_data['upload'])) {
    if (isset($post_data['action'])) {
        $apiOutput = match ($post_data['action']) {
            'favorite' => [
                "favorited" => true,
                "number" => rand(0, 47101),
            ],
            'rate' => [
                rate($post_data['number'], $post_data['upload']),
            ],
            'unrate' => [
                unrate($post_data['upload']),
            ],
            'like' => [
                rate(5, $post_data['upload']),
            ],
            'dislike' => [
                rate(1, $post_data['upload']),
            ],
            default => [
                "error" => "This interaction type is invalid."
            ],
        };
    }
}

echo json_encode($apiOutput);
