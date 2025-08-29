<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

use BluffingoCore\CoreUtilities;
use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\Utilities;
use SquareBracket\UserRoleEnum;
use SquareBracket\UserFlags;
use SquareBracket\UserCustomizationData;

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /user/$new_username/comments");
        exit();
    } else {
        Utilities::notifyBanner("notify_invalid_user", "/");
    }
}

if ($user_ban_data = $database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$data["id"]])) {
    if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
        Utilities::notifyBanner("notify_banned_user", "/");
    }
}

$flags = UserFlags::toArray($data["u_flags"]);

if ($flags["profile_customization_enabled"]) {
    $profile_customization_data = new UserCustomizationData($database, $data["id"]);
} else {
    $profile_customization_data = null;
}

// page-specific shit Here.

$comment_data = new CommentData($database, CommentLocation::Profile, $data["id"]);
$comments = $comment_data->getComments();

$data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["customcolor"],
    "about" => ($data["about"] ?? null),
    "customization" => $profile_customization_data?->getData() ?? false,
    "comments" => $comments,
];

echo $twig->render("profile_comments.twig", [
    'data' => $data,
]);
