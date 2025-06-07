<?php

namespace OpenSB;

global $database;

use SquareBracket\CommentData;
use SquareBracket\CommentLocation;

header('Content-Type: application/json');

$id = ($_GET['upload'] ?? null);

$comments = new CommentData($database, CommentLocation::Upload, $id);

$comment_data = $comments->getComments();
$comment_count = $comments->getCommentCount();

function handleCommentData($data) {
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