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

global $auth, $sb, $twig;

use Data\Notification\NotificationEnum;
use Data\Upload\UploadFlags;
use Data\User\UserData;
use Core\Utilities;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$userId = $auth->getUserID();

$apiOutput = [
    "error" => "This request is invalid."
];

if ($auth->isBanned()) {
    echo json_encode(["error" => "You are banned."]);
    exit;
}

if ($auth->getUserFlags(true)["unverified"]) {
    echo json_encode(["error" => "You are not verified yet."]);
    exit;
}

if (!isset($post_data['type']) || !isset($post_data['comment'])) {
    echo json_encode($apiOutput);
    exit;
}

$database = $sb->getDatabaseClass();
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

if (!$sb->isDebug()) {
    $timeLimit = time() - 15;
    if (
        $database->result("SELECT COUNT(*) FROM upload_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM user_profile_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM journal_comments WHERE timestamp > ? AND author = ?", [$timeLimit, $userId])
    ) {
        echo json_encode(["error" => "Please wait at least 15 seconds before commenting again."]);
        exit;
    }
}

$id = $post_data["id"];
$replyTo = $post_data['reply_to'] ?? 0;

if ($post_data['type'] == 'upload') {
    $upload_flags = UploadFlags::toArray($database->result("SELECT flags from uploads where upload_id = ?", [$id]));

    if ($upload_flags["block_comments"]) {
        echo json_encode(["error" => "Commenting has been disabled on this upload."]);
        exit;
    }
}

switch ($post_data['type']) {
    case 'upload':
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
    "INSERT INTO {$table} (location_id, reply_to, comment, author, timestamp) VALUES (?,?,?,?,?)",
    [$id, $replyTo, $commentText, $userId, time()]
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

// temporary code, will be removed in beta 2. -chaziz 2/28/2026
if ($sb->getLocalOptions()["skin"] == "trinium") {
    $template = 'components/_comment.twig';
} else {
    $template = 'components/comment.twig';
}

$html = $twig->render($template, ['comment' => $comment]);

// not gonna put this code in the first switch case YET due to a weird ass hack i have to do with upload comments

// for replies, this should most likely be recursive and notify everyone in a 
// comment reply thread, but that's going to be for opensb 2.1. -chaziz 9/18/2025
switch ($post_data['type']) {
    case 'upload':
        // comments use the upload's string id and not the numeric id as the location of an upload, so we have to do
        // this weird shit.
        $numericID = Utilities::uploadStringIDToUploadNumericID($database, $post_data["id"]);

        if ($replyTo) {
            // get author of the comment we're replying to
            $recipient = $database->result("SELECT author FROM upload_comments WHERE id = ?", [$replyTo]);
        } else {
            // get author of the upload
            $recipient = $database->result("SELECT author FROM uploads WHERE upload_id = ?", [$post_data["id"]]);
        }

        if ($recipient != $auth->getUserID()) {
            Utilities::notifyUser($database, $recipient, $numericID, $insertID, NotificationEnum::CommentUpload);
        }
        break;
    case 'profile':
        if ($replyTo) {
            // get author of the comment we're replying to
            $recipient = $database->result("SELECT author FROM user_profile_comments WHERE id = ?", [$replyTo]);
        } else {
            // get author of the upload
            $recipient = $id;
        }

        if ($recipient != $auth->getUserID()) {
            Utilities::notifyUser($database, $recipient, $id, $insertID, NotificationEnum::CommentProfile);
        }
        break;
    case 'journal':
        if ($replyTo) {
            // get author of the comment we're replying to
            $recipient = $database->result("SELECT author FROM journal_comments WHERE id = ?", [$replyTo]);
        } else {
            // get author of the upload
            $recipient = $database->result("SELECT author FROM journals WHERE id = ?", [$post_data["id"]]);
        }

        Utilities::notifyUser($database, $recipient, $id, $insertID, NotificationEnum::CommentJournal);
        break;
    default:
        exit;
}

if ($sb->isDiscordWebhookEnabled()) {
    $data = [
        'id' => $insertID,
        'location_id' => $post_data['id'],
        'contents' => $commentText,
        'author' => $auth->getUserData()["name"],
        'type' => $post_data['type']
    ];

    $sb->getDiscordWebhookClass()->newCommentHook($data);
}

$apiOutput = [
    "comment" => $comment,
    "html" => $html,
];

echo json_encode($apiOutput);
