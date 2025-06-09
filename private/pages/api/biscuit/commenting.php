<?php

namespace OpenSB;

global $auth, $orange, $twig;

use SquareBracket\NotificationEnum;
use SquareBracket\UserData;
use SquareBracket\Utilities;

$post_data = json_decode(file_get_contents('php://input'), true);

$userId = $auth->getUserID();

$apiOutput = [
    "error" => "Invalid request."
];

if ($auth->getUserBanData()) {
    echo json_encode(["error" => "User is banned."]);
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
    echo json_encode(["error" => "This comment is invalid."]);
    exit;
}

if (strlen($commentText) > 1000) {
    echo json_encode(["error" => "This comment is too long."]);
    exit;
}

if (!$orange->isDebug()) {
    $timeLimit = time() - 15;
    if ($database->result("SELECT COUNT(*) FROM upload_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM user_profile_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM journal_comments WHERE date > ? AND author = ?", [$timeLimit, $userId])
    ) {
        // TODO: display a ui notification
        echo json_encode(["error" => "Please wait at least 15 seconds before commenting again."]);
        exit;
    }
}

$id = $post_data["id"];
$replyTo = $post_data['reply_to'] ?? 0;

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

$apiOutput = [
    "comment" => $comment,
    "html" => $html,
];

echo json_encode($apiOutput);