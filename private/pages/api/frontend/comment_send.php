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

namespace OpenSB;

global $auth, $orange, $twig;

use SquareBracket\NotificationEnum;
use SquareBracket\UploadFlags;
use SquareBracket\UserData;
use SquareBracket\Utilities;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$userId = $auth->getUserID();

$apiOutput = [
    "error" => "Invalid request."
];

if ($auth->getUserBanData()) {
    echo json_encode(["error" => "User is banned."]);
    exit;
}

if ($auth->getUserFlags(true)["unverified"]) {
    echo json_encode(["error" => "You have been not verified yet."]);
    exit;
}

if (!isset($post_data['type']) || !isset($post_data['comment'])) {
    echo json_encode($apiOutput);
    exit;
}

$database = $orange->getDatabaseClass();
$author = new UserData($database, $auth->getUserID());
$commentText = trim($post_data['comment']);

if ($commentText === "") {
    echo json_encode(["error" => "Please write your comment."]);
    exit;
}

if (strlen($commentText) > 1000) {
    echo json_encode(["error" => "This comment is too long."]);
    exit;
}

if (!$orange->isDebug()) {
    $timeLimit = time() - 15;
    if (
        $database->result("SELECT COUNT(*) FROM upload_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM user_profile_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM journal_comments WHERE date > ? AND author = ?", [$timeLimit, $userId])
    ) {
        echo json_encode(["error" => "Please wait at least 15 seconds before commenting again."]);
        exit;
    }
}

$id = $post_data["id"];
$replyTo = $post_data['reply_to'] ?? 0;

if ($post_data['type'] == 'submission') {
    $upload_flags = UploadFlags::toArray($database->result("SELECT flags from uploads where video_id = ?", [$id]));

    if ($upload_flags["block_comments"]) {
        echo json_encode(["error" => "Commenting has been disabled on this upload."]);
        exit;
    }
}

switch ($post_data['type']) {
    case 'submission':
        $table = 'upload_comments';
        break;
    case 'profile':
        $table = 'user_profile_comments';
        break;
    case 'journal':
        $table = 'journal_comments';
        break;
    default:
        echo json_encode(["error" => "Invalid comment type."]);
        exit;
}

$database->query(
    "INSERT INTO {$table} (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
    [$id, $replyTo, $commentText, $userId, time(), 0]
);

// we need the insertid right before we call anything else to avoid a "trying to access array offset on false" warning.
$insertID = $database->insertID();

$comment = [
    "id" => $insertID,
    "posted_id" => $post_data['id'],
    "post" => $commentText,
    "posted" => time(),
    "author" => [
        "id" => $auth->getUserID(),
        "info" => $author->getUserArray(),
    ],
    "replies" => []
];

$html = $twig->render('components/_comment.twig', ['comment' => $comment]);

// not gonna put this code in the first switch case YET due to a weird ass hack i have to do with upload comments
switch ($post_data['type']) {
    case 'submission':
        // comments use the upload's string id and not the numeric id as the location of an upload, so we have to do
        // this weird shit.
        $numericID = Utilities::uploadStringIDToUploadNumericID($database, $post_data["id"]);

        Utilities::notifyUser($database, 1, $numericID, $insertID, NotificationEnum::CommentUpload);
        break;
    case 'profile':
        Utilities::notifyUser($database, 1, $id, $insertID, NotificationEnum::CommentProfile);
        break;
    case 'journal':
        Utilities::notifyUser($database, 1, $id, $insertID, NotificationEnum::CommentJournal);
        break;
    default:
        exit;
}

if ($orange->isDiscordWebhookEnabled()) {
    $data = [
        'id' => $insertID,
        'name' => $post_data['id'],
        'contents' => $commentText,
        'author' => $auth->getUserData()["name"],
        'type' => $post_data['type']
    ];

    $orange->getDiscordWebhookClass()->newCommentHook($data);
}

$apiOutput = [
    "comment" => $comment,
    "html" => $html,
];

echo json_encode($apiOutput);
