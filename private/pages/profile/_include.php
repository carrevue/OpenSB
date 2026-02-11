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

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;
use OpenSB\UserData;
use OpenSB\UserFlags;
use OpenSB\UserCustomizationData;
use OpenSB\FakeUser;

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, attempt to fetch the user's current name through their id
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        if ($new_username) {
            // TODO: handle paths (currently: /user/OldName/subpage will redirect to /user/NewName)
            Utilities::redirect('/user/' . $new_username, 301);
        } else {
            // if for whatever reason this leads to nowhere (eg: deleted user or 
            // half-assed prod blacklisting), return to homepage.
            Utilities::notifyBanner("notify_invalid_user", "/");
        }
    } else {
        // check if this could be a fake user
        $fake_user_data = FakeUser::getFakeUserFromName($username);
        if ($fake_user_data) {
            $data = $fake_user_data;
        } else {
            Utilities::notifyBanner("notify_invalid_user", "/");
        }
    }
}

if ($user_ban_data = $database->fetch("SELECT * FROM user_bans WHERE user = ?", [$data["id"]])) {
    if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
        Utilities::notifyBanner("notify_banned_user", "/");
    }
}

$flags = UserFlags::toArray($data["flags"]);

if ($flags["profile_customization_enabled"]) {
    $profile_customization_data = new UserCustomizationData($database, $data["id"]);
} else {
    $profile_customization_data = null;
}

$is_own_profile = $data["id"] == $auth->getUserID();

// right. cheat "related channels" on finalium profiles by using featured users
if ($sb->getLocalOptions()["skin"] == "finalium") {
    // ripped from SquareBracketTwigExtension
    $users = $database->fetchArray(
        $database->query(
            "SELECT u.id
            FROM users u 
            WHERE u.flags & ? = ?",
            [UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value]
        )
    );

    $related_users = [];

    foreach ($users as $user) {
        $user = new UserData($database, $user["id"]);
        $related_users[] = $user->getUserArray();
    }
}

// this is kind of ugly. -chaziz 02/11/2026
$localization = $sb->getLocalizationClass();
$storage = $sb->getStorageClass();

$twig->setPageMeta([
    "opengraph_type" => "profile",
    "opengraph_username" => $data["name"],
    "opengraph_description" => (!empty(trim($data["about"])) ? $data["about"] : $localization->translate("profile_no_description")),
    "opengraph_image" => $storage->getUserProfilePicture($data["id"], false),
    "opengraph_section" => "Profile",
]);