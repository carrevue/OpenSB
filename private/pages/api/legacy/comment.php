<?php

namespace OpenSB;

global $auth, $database, $twig, $orange;

use SquareBracket\UserData;

// simple shit fix for shitty finalium bug that dates from 2021 -chaziz 4/12/2023
if ($_POST["comment"] == "")
{
    die("This comment is invalid.");
}

// Fuck -chaziz 4/19/2023
if (strlen($_POST["comment"]) > 1000) {
    die("This comment is too long.");
}

// apparantly this wasnt a thing in the legacy api? oops -chaziz 4/20/2025
if (!$orange->isDebug()) {
    $timeLimit = time() - 15;
    if ($database->result("SELECT COUNT(*) FROM upload_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM user_profile_comments WHERE date > ? AND author = ?", [$timeLimit, $userId]) ||
        $database->result("SELECT COUNT(*) FROM journal_comments WHERE date > ? AND author = ?", [$timeLimit, $userId])
    ) {
        die("Please wait at least 15 seconds before commenting again.");
    }
}

$type = 0;

if (isset($_POST['really'])) {
    switch ($_POST['type']) {
        case "video":
            $type = 0;
            $table = "upload_comments";
            $id = ($_POST['vidid'] ?? "");
            $reply_to = ($_POST['reply_to'] ?? "0");
            break;
        case "profile":
            $type = 1;
            $table = "user_profile_comments";
            $id = ($_POST['uid'] ?? "");
            $reply_to = ($_POST['reply_to'] ?? "0");
            break;
        case "journal":
            $type = 2;
            $table = "journal_comments";
            $id = ($_POST['uid'] ?? "");
            $reply_to = ($_POST['reply_to'] ?? "0");
            break;
    }
} else {
    die("this is invalid");
}

$author = new UserData($database, $auth->getUserID());

$comment = [
    "id" => 123456789,
    "posted_id" => 987654321,
    "post" => $_POST['comment'],
    "posted" => time(),
    "author" => [
        "id" => $auth->getUserID(),
        "info" => $author->getUserArray(),
    ],
];

if ($type == 0) {
    $database->query("INSERT INTO upload_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]);
} elseif ($type == 1) {
    $database->query("INSERT INTO user_profile_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]);
} elseif ($type == 2) {
    $database->query("INSERT INTO journal_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]);
} else {
    die("this is still invalid");
}

echo $twig->render('components/comment.twig', [
    'data' => $comment
]);