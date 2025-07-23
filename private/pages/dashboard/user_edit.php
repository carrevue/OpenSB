<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $auth, $twig, $database, $orange, $path;

use SquareBracket\UserData;
use SquareBracket\UserFlags;
use SquareBracket\Utilities;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("Please login using your dashboard access password.", "/dashboard/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

$username = $path[3] ?? null;

$user = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$user) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /dashboard/users/$new_username");
        exit();
    } else {
        Utilities::notifyBanner("This user does not exist.", "/dashboard/");
    }
}

if (isset($_POST['ban_user'])) {
    // Don't ban non-existent users.
    if (!$database->fetch("SELECT u.name FROM users u WHERE u.name = ?", [$_POST["ban_user"]])) {
        Utilities::notifyBanner("This user does not exist.", "/dashboard/users/");
    }
    // Don't ban mods/dashboards.
    if ($database->fetch("SELECT u.powerlevel FROM users u WHERE u.name = ?", [$_POST["ban_user"]])["powerlevel"] != 1) {
        Utilities::notifyBanner("This user cannot be banned.", "/dashboard/users/");
    }
    // Check if user is already banned, if not, then ban. Otherwise, unban.
    $id = $database->fetch("SELECT u.id FROM users u WHERE u.name = ?", [$_POST["ban_user"]])["id"];
    if ($database->fetch("SELECT b.userid FROM user_bans b WHERE b.userid = ?", [$id])) {
        $database->query("DELETE FROM user_bans WHERE userid = ?", [$id]);
        Utilities::notifyBanner("Unbanned " . $_POST["ban_user"] . '.', "/dashboard/users", "success");
    } else {
        $database->query(
            "INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
            [$id, "Banned by " . $auth->getUserData()["name"], time()]
        );
        Utilities::notifyBanner("Banned " . $_POST["ban_user"] . '.', "/dashboard/users", "success");
    }
}

if ($user["ip"] != "999.999.999.999") {
    $users_with_matching_ips = $database->fetchArray($database->query(
        "SELECT u.name, u.title FROM users u WHERE u.ip = ? AND id != ?",
        [$user["ip"], $user["id"]]
    ));
} else {
    $users_with_matching_ips = [];
}

$old_username_data = $database->fetchArray($database->query("SELECT * FROM user_old_names WHERE user = ?", [$user["id"]]));

// there is currently no way of posting staff notes on the site.
$notes = $database->fetchArray($database->query("SELECT * FROM user_staff_notes WHERE user = ?", [$user["id"]]));

$notes_proper = [];

foreach ($notes as $note) {
    $userData = new UserData($database, $note["author"]);
    $notes_proper[] = [
        "content" => $note["note"],
        "time" => $note["time"],
        "author" => [
            "id" => $note["author"],
            "info" => $userData->getUserArray(),
        ],
    ];
}

// unlike uploads, there is no proper implementation of getting user data that isnt intended for
// simply getting basic user data via the UserData class.
$flags = UserFlags::toArray($user["u_flags"]);

echo $twig->render("dashboard_user_edit.twig", [
    'user' => $user,
    'flags' => $flags,
    'users_with_matching_ips' => $users_with_matching_ips,
    'notes' => $notes_proper,
    'old_names' => $old_username_data
]);
