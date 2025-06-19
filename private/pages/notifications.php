<?php

namespace OpenSB;

global $twig, $database, $auth;

use SquareBracket\NotificationEnum;
use SquareBracket\UserData;
use SquareBracket\Utilities;

function typeToIntro($type)
{
    switch (NotificationEnum::from($type)) {
        default:
            $intro = NotificationEnum::from($type)->name . " Intro";
            break;
        case NotificationEnum::CommentProfile:
        case NotificationEnum::CommentJournal:
        case NotificationEnum::CommentUpload:
            $intro = "Comment by ";
            break;
        case NotificationEnum::NewUpload:
            $intro = "Upload by";
            break;
        case NotificationEnum::NewJournal:
            $intro = "Journal by";
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

            if (str_ends_with($profile["name"], "s")) {
                $data["origin"] = $profile["name"] . "' profile";
            } else {
                $data["origin"] = $profile["name"] . "'s profile";
            }
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

        case NotificationEnum::NewUpload:
            $data["info"] = "Upload Title";
            $data["origin"] = "Upload Title";
            break;

        case NotificationEnum::NewJournal:
            $data["info"] = "Journal Title";
            $data["origin"] = "Journal Title";
            break;

        case NotificationEnum::UserRename:
            $data["info"] = "From now on, all references to this user will display this name.";
            $data["origin"] = false;
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