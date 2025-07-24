<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021-2022 icanttellyou

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

global $auth, $database, $twig, $orange;

use SquareBracket\UploadFlags;
use SquareBracket\UserData;

// simple shit fix for shitty finalium bug that dates from 2021 -chaziz 4/12/2023
if ($_POST["comment"] == "") {
    die("This comment is invalid.");
}

// Fuck -chaziz 4/19/2023
if (strlen($_POST["comment"]) > 1000) {
    die("This comment is too long.");
}

// apparantly this wasnt a thing in the legacy api? oops -chaziz 4/20/2025
if (!$orange->isDebug()) {
    $timeLimit = time() - 15;
    if (
        $database->result("SELECT COUNT(*) FROM upload_comments WHERE date > ? AND author = ?", [$timeLimit, $auth->getUserID()]) ||
        $database->result("SELECT COUNT(*) FROM user_profile_comments WHERE date > ? AND author = ?", [$timeLimit, $auth->getUserID()]) ||
        $database->result("SELECT COUNT(*) FROM journal_comments WHERE date > ? AND author = ?", [$timeLimit, $auth->getUserID()])
    ) {
        die("Please wait at least 15 seconds before commenting again.");
    }
}

$type = 0;

$id = "";
$reply_to = 0;

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
            $id = ($_POST['jid'] ?? "");
            $reply_to = ($_POST['reply_to'] ?? "0");
            break;
    }
} else {
    die("this is invalid");
}

if ($_POST['type'] == 'video') {
    $upload_flags = UploadFlags::toArray($database->result("SELECT flags from uploads where video_id = ?", [$id]));

    if ($upload_flags["block_comments"]) {
        die("Commenting has been disabled on this upload.");
    }
}

if ($type == 0) {
    $database->query(
        "INSERT INTO upload_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]
    );
} elseif ($type == 1) {
    $database->query(
        "INSERT INTO user_profile_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]
    );
} elseif ($type == 2) {
    $database->query(
        "INSERT INTO journal_comments (id, reply_to, comment, author, date, deleted) VALUES (?,?,?,?,?,?)",
        [$id, $reply_to, $_POST['comment'], $auth->getUserID(), time(), 0]
    );
} else {
    die("this is still invalid");
}

$insertID = $database->insertID();

$author = new UserData($database, $auth->getUserID());

$comment = [
    "id" => $insertID,
    "posted_id" => $id,
    "post" => $_POST['comment'],
    "posted" => time(),
    "author" => [
        "id" => $auth->getUserID(),
        "info" => $author->getUserArray(),
    ],
];

if ($orange->isDiscordWebhookEnabled()) {
    //$data = [
    $webhook_data = [
        'id' => $insertID,
        'name' => $id,
        'contents' => $_POST['comment'],
        'author' => $auth->getUserData()["name"],
        'type' => $_POST['type'],
    ];

    $orange->getDiscordWebhookClass()->newCommentHook($webhook_data, true);
}

echo $twig->render('components/comment.twig', [
    'data' => $comment
]);
