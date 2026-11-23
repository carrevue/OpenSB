<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace Pages\Dashboard;

global $auth, $twig, $database, $sb;

use Core\Utilities;
use Data\User\UserRoleEnum;
use Data\User\UserData;

include_once('_include.php');

$user = $database->fetch("SELECT u.*, (SELECT COUNT(*) FROM user_bans WHERE user = u.id) AS is_banned FROM users u WHERE u.name = ?", [$username]);

if (!$user) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /dashboard/users/$new_username/follows");
        exit();
    } else {
        Utilities::notifyBanner("notify_invalid_user", "/dashboard/users");
    }
}

function handle_users($raw_data, $database) {
    $user_array = [];

    foreach ($raw_data as $key => $value) {
        $uid = $value["user_id"];
        $user_data = new UserData($database, $uid);

        $user_array[$uid]["banned"] = $user_data->isUserBanned();
        $user_array[$uid]["info"] = $user_data->getUserArray();
    }
    return $user_array;
}

// users that the current user is following
$following = $database->fetchArray($database->query("SELECT id as user_id FROM user_follows WHERE user = ?", [$user["id"]]));

// users that are following the current user
$followers = $database->fetchArray($database->query("SELECT user as user_id FROM user_follows WHERE id = ?", [$user["id"]]));

echo $twig->render("user_follows.twig", [
    'user' => $user,
    "following" => handle_users($following, $database),
    "followers" => handle_users($followers, $database)
]);
