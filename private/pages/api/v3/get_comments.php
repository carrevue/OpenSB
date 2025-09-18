<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $database;

use OpenSB\CommentData;
use OpenSB\CommentLocation;

header('Content-Type: application/json');

$id = ($_GET['upload'] ?? null);

$comments = new CommentData($database, CommentLocation::Upload, $id);

$comment_data = $comments->getComments();
$comment_count = $comments->getCommentCount();

function handleCommentData($data)
{
    $output = [];
    foreach ($data as $comment) {
        $commentEntry = [
            'id' => $comment['id'],
            'post' => $comment['post'],
            "posted" => $comment['posted'],
            'author' => [
                'id' => $comment['author']['id'],
                'username' => $comment['author']['info']['username'],
                'displayname' => $comment['author']['info']['displayname'],
                'color' => $comment['author']['info']['color'],
            ],
            'replies' => handleCommentData($comment['replies']),
        ];

        $output[] = $commentEntry;
    }
    return $output;
}

$apiOutput = handleCommentData($comment_data);

echo json_encode(array('comments' => $apiOutput, 'count' => $comment_count));
