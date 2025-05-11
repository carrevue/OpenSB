<?php

namespace OpenSB;

global $auth, $orange;

use SquareBracket\NotificationEnum;
use SquareBracket\Utilities;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if ($auth->getUserBanData()) {
    $apiOutput = [
        "error" => "You have been banned."
    ];
}

$database = $orange->getDatabaseClass();

function follow($member): array
{
    global $database, $auth;

    if ($member == $auth->getUserID()) {
        return [
            "error" => "User attempting to follow themself."
        ];
    }

    if ($database->result("SELECT COUNT(user) FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $member]) != 0) {
        $database->query("DELETE FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $member]);
        $result = false;
    } else {
        $database->query("INSERT INTO user_follows (id, user) VALUES (?,?)", [$member, $auth->getUserID()]);
        $result = true;

        Utilities::notifyUser($database, $member, 0,0,NotificationEnum::Follow);
    }

    $number = $database->fetch("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$member])['COUNT(user)'];

    if ($result) {
        $text = "Unfollow";
    } else {
        $text = "Follow";
    }

    return [
        "followed" => $result,
        "number" => $number,
        "text" => $text,
    ];
}

if (isset($post_data['member'])) {
    if (isset($post_data['action'])) {
        $apiOutput = match ($post_data['action']) {
            'follow' => follow($post_data['member']),
            default => [
                "error" => "This interaction type is invalid or has not yet been implemented."
            ],
        };
    }
}

echo json_encode($apiOutput);
