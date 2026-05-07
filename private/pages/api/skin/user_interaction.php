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

global $auth, $sb;

use Data\Notification\NotificationEnum;
use Core\Utilities;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if ($auth->isBanned()) {
    $apiOutput = [
        "error" => "You have been banned."
    ];
}

function follow($member): array
{
    global $sb, $database, $auth;

    // cant put this outside here otherwise it shits out, so thats definitely something
    $localization = $sb->getLocalizationClass();

    $number = $database->result("SELECT f_index FROM users WHERE id = ?", [$member]);

    if ($member == $auth->getUserID()) {
        return [
            "error" => "You cannot follow yourself."
        ];
    }

    if ($database->result("SELECT COUNT(user) FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $member]) != 0) {
        $database->query("DELETE FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $member]);
        $result = false;
        $number--;
    } else {
        $database->query("INSERT INTO user_follows (id, user) VALUES (?,?)", [$member, $auth->getUserID()]);
        $result = true;
        $number++;

        Utilities::notifyUser($database, $member, 0, 0, NotificationEnum::Follow);
    }

    $database->query("UPDATE users SET f_index = ? WHERE id = ?", [$number, $member]);

    if ($result) {
        $text = $localization->translate("unfollow");
    } else {
        $text = $localization->translate("follow");
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
                "error" => "This interaction type is invalid or unimplemented."
            ],
        };
    }
}

echo json_encode($apiOutput);
