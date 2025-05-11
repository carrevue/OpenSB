<?php

namespace OpenSB;

global $twig, $database, $auth;

use SquareBracket\NotificationEnum;
use SquareBracket\UserData;
use SquareBracket\Utilities;

function typeToName($type)
{
    $name = "generic";

    switch (NotificationEnum::from($type)) {
        case NotificationEnum::CommentUpload:
            $name = "comment_upload";
            break;
        case NotificationEnum::CommentProfile:
            $name = "comment_profile";
            break;
        case NotificationEnum::CommentJournal:
            $name = "comment_journal";
            break;
        case NotificationEnum::UploadTakedown:
            $name = "upload_takedown";
            break;
        case NotificationEnum::Follow:
            $name = "user_follow";
            break;
    }

    return $name;
}

function typeToIntro($type)
{
    $intro = "Generic notice by ";

    switch (NotificationEnum::from($type)) {
        case NotificationEnum::CommentProfile:
        case NotificationEnum::CommentJournal:
        case NotificationEnum::CommentUpload:
            $intro = "Comment by ";
            break;
        case NotificationEnum::UploadTakedown:
            $intro = "Your upload has been taken down.";
            break;
        case NotificationEnum::Follow:
            $intro = "Followed by ";
            break;
    }

    return $intro;
}

function getRequiredData($database, $notice)
{
    $data = [];

    switch (NotificationEnum::from($notice["type"])) {
        case NotificationEnum::Follow:
            $data["info"] = "This user is now following your profile.";
            $data["origin"] = false;
            break;

        case NotificationEnum::CommentProfile:
            $comment = $database->fetch("SELECT c.comment_id, c.id, c.comment, c.author, c.date, c.deleted FROM user_profile_comments c WHERE c.comment_id = ?", [$notice["related_id"]]);
            $profile = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"];
            $data["origin"] = $profile["name"] . "'s profile";
            break;

        case NotificationEnum::CommentJournal:
            $comment = $database->fetch("SELECT c.comment_id, c.id, c.comment, c.author, c.date, c.deleted FROM journal_comments c WHERE c.comment_id = ?", [$notice["related_id"]]);
            $journal = $database->fetch("SELECT title FROM journals WHERE id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"];
            $data["origin"] = $journal["title"];
            break;

        case NotificationEnum::CommentUpload:
            $comment = $database->fetch("SELECT c.comment_id, c.id, c.comment, c.author, c.date, c.deleted FROM upload_comments c WHERE c.comment_id = ?", [$notice["related_id"]]);
            $upload = $database->fetch("SELECT v.video_id, v.author, v.title FROM uploads v WHERE v.id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"];
            $data["origin"] = $upload["title"] ?? "Unknown upload";
            break;
    }

    return $data;
}

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if (isset($_GET["action"])) {
    if ($_GET["action"] == "clear_all") {
        $database->query("DELETE FROM user_notifications WHERE recipient = ?;", [$auth->getUserID()]);

        Utilities::notifyBanner("Cleared.", "/notices", "success");
    }
}

$data = $database->fetchArray($database->query("SELECT * FROM user_notifications WHERE recipient = ? ORDER BY id DESC", [$auth->getUserID()]));

$noticeData = [];

foreach ($data as $notice) {
    $userData = new UserData($database, $notice["sender"]);

    $noticeData[] = [
        "id" => $notice["id"],
        "type" => typeToName($notice["type"]),
        "sender" => [
            "id" => $notice["sender"],
            "info" => $userData->getUserArray(),
        ],
        "time" => $notice["timestamp"],
        "intro" => typeToIntro($notice["type"]),
        "detail" => getRequiredData($database, $notice),
    ];
}

echo $twig->render('notifications.twig', [
    'data' => $noticeData,
]);