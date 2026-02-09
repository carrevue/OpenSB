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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb, $path;

use OpenSB\UserData; // only used for staff notes authors, do NOT use this for actual user data
use OpenSB\UserFlags;
use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "primary", ["Trinium"]);
}

function discord_webhook_notify($sb, $auth, $user, $action)
{
    $data = [
        'user' => $user,
        'author' => $auth->getUserData()["name"],
        'action' => $action,
    ];

    $sb->getDiscordWebhookClass()->dashboardUserHook($data);
}

$user = $database->fetch("SELECT u.*, (SELECT COUNT(*) FROM user_bans WHERE user = u.id) AS is_banned FROM users u WHERE u.name = ?", [$username]);

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
        Utilities::notifyBanner("notify_invalid_user", "/dashboard/users");
    }
}

// unlike uploads, there is no proper implementation of getting user data that isnt intended for
// simply getting basic user data via the UserData class.
$flags = $user["flags"];
$flags_array = UserFlags::toArray($user["flags"]);

if (isset($_POST['ban_user'])) {
    // Don't ban non-existent users.
    if (!$database->fetch("SELECT u.name FROM users u WHERE u.name = ?", [$_POST["ban_user"]])) {
        Utilities::notifyBanner("notify_invalid_user", "/dashboard/users/");
    }
    // Don't ban staff.
    if ($database->fetch("SELECT u.powerlevel FROM users u WHERE u.name = ?", [$_POST["ban_user"]])["powerlevel"] != 1) {
        Utilities::notifyBanner("notify_no_permission", "/dashboard/user/{$username}");
    }

    if ($database->fetch("SELECT b.user FROM user_bans b WHERE b.user = ?", [$user["id"]])) {
        $database->query("DELETE FROM user_bans WHERE user = ?", [$user["id"]]);

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["ban_user"], 'unbanned');
        }

        Utilities::notifyBanner("notify_dashboard_unban_success", "/dashboard/users/{$username}", "success", [$_POST["ban_user"]]);
    } else {
        $database->query(
            "INSERT INTO user_bans (user, reason, timestamp) VALUES (?,?,?)",
            [$user["id"], "Banned by " . $auth->getUserData()["name"], time()]
        );

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["ban_user"], 'banned');
        }

        Utilities::notifyBanner("notify_dashboard_ban_success", "/dashboard/users/{$username}", "success", [$_POST["ban_user"]]);
    }
}

if (isset($_POST['verify_user'])) {
    // Don't (un)verify non-existent users.
    if (!$database->fetch("SELECT u.name FROM users u WHERE u.name = ?", [$_POST["verify_user"]])) {
        Utilities::notifyBanner("notify_invalid_user", "/dashboard/users/");
    }
    // Don't (un)verify staff.
    if ($database->fetch("SELECT u.powerlevel FROM users u WHERE u.name = ?", [$_POST["verify_user"]])["powerlevel"] != 1) {
        Utilities::notifyBanner("notify_no_permission", "/dashboard/user/{$username}");
    }

    if ($flags & UserFlags::FLAG_UNVERIFIED->value) {
        $flags &= ~UserFlags::FLAG_UNVERIFIED->value;

        $database->query(
            "UPDATE users SET flags = ? WHERE id = ?",
            [$flags, $user["id"]]
        );

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["verify_user"], 'verified');
        }

        Utilities::notifyBanner("notify_dashboard_verify_success", "/dashboard/users/{$username}", "success", [$_POST["verify_user"]]);
    } else {
        $flags |= UserFlags::FLAG_UNVERIFIED->value;

        $database->query(
            "UPDATE users SET flags = ? WHERE id = ?",
            [$flags, $user["id"]]
        );

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["verify_user"], 'unverified');
        }

        Utilities::notifyBanner("notify_dashboard_unverify_success", "/dashboard/users/{$username}", "success", [$_POST["verify_user"]]);
    }
}

if (isset($_POST['feature_user'])) {
    // Don't (un)feature non-existent users.
    if (!$database->fetch("SELECT u.name FROM users u WHERE u.name = ?", [$_POST["feature_user"]])) {
        Utilities::notifyBanner("notify_invalid_user", "/dashboard/users/");
    }

    if ($flags & UserFlags::FLAG_FEATURED->value) {
        $flags &= ~UserFlags::FLAG_FEATURED->value;

        $database->query(
            "UPDATE users SET flags = ? WHERE id = ?",
            [$flags, $user["id"]]
        );

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["feature_user"], 'unfeatured');
        }

        Utilities::notifyBanner("notify_dashboard_unfeature_user_success", "/dashboard/users/{$username}", "success", [$_POST["feature_user"]]);
    } else {
        $flags |= UserFlags::FLAG_FEATURED->value;

        $database->query(
            "UPDATE users SET flags = ? WHERE id = ?",
            [$flags, $user["id"]]
        );

        if ($sb->isDiscordWebhookEnabled()) {
            discord_webhook_notify($sb, $auth, $_POST["feature_user"], 'featured');
        }

        Utilities::notifyBanner("notify_dashboard_feature_user_success", "/dashboard/users/{$username}", "success", [$_POST["feature_user"]]);
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
        "time" => $note["timestamp"],
        "author" => [
            "id" => $note["author"],
            "info" => $userData->getUserArray(),
        ],
    ];
}

if ($sb->isIpLookupEnabled() && $auth->userHasRole(UserRoleEnum::Administrator)) {
    $ip_info = $sb->getIpLookupClass()->getInfo($user["ip"]);
} else {
    $ip_info = [];
}

echo $twig->render("dashboard_user_edit.twig", [
    'user' => $user,
    'flags' => $flags_array,
    'users_with_matching_ips' => $users_with_matching_ips,
    'notes' => $notes_proper,
    'old_names' => $old_username_data,
    'ip_info' => $ip_info,
]);
