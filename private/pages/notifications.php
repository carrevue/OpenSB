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

namespace OpenSB\Pages;

global $twig, $database, $auth;

use OpenSB\NotificationEnum;
use OpenSB\UserData;
use OpenSB\Utilities;

// TODO: localize all of this shit

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
            // i'm pretty sure this should use result() rather than fetch()
            $comment = $database->fetch("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM user_profile_comments c WHERE c.id = ?", [$notice["related_id"]]);
            $profile = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"] ?? '';

            if (!isset($profile["name"])) {
                $profile = [
                    "name" => "InvalidUser!"
                ];
            }

            if (str_ends_with($profile["name"], "s")) {
                $data["origin"] = $profile["name"] . "' profile";
            } else {
                $data["origin"] = $profile["name"] . "'s profile";
            }
            break;

        case NotificationEnum::CommentJournal:
            $comment = $database->fetch("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM journal_comments c WHERE c.id = ?", [$notice["related_id"]]);
            $journal = $database->fetch("SELECT title FROM journals WHERE id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"] ?? "";
            $data["origin"] = $journal["title"] ?? "Unknown journal";
            break;

        case NotificationEnum::CommentUpload:
            $comment = $database->fetch("SELECT c.id, c.location_id, c.comment, c.author, c.timestamp FROM upload_comments c WHERE c.id = ?", [$notice["related_id"]]);
            $upload = $database->fetch("SELECT v.upload_id, v.author, v.title FROM uploads v WHERE v.id = ?", [$notice["level"]]);

            $data["info"] = $comment["comment"] ?? "";
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

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if (isset($_GET["action"])) {
    if ($_GET["action"] == "clear_all") {
        $database->query("DELETE FROM user_notifications WHERE recipient = ?;", [$auth->getUserID()]);

        Utilities::notifyBanner("notify_notifications_cleared", "/notifications", "success");
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
