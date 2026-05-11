<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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

namespace Pages;

global $auth, $twig, $database, $sb, $path;

use Data\User\UserData; // only used for staff notes authors, do NOT use this for actual user data
use Data\User\UserFlags;
use Core\Utilities;
use Data\User\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getCurrentSkinName() != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
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

if ($sb->isIpLookupEnabled() && $auth->userHasRole(UserRoleEnum::Moderator)) {
    $ip_info = $sb->getIpLookupClass()->getInfo($user["ip"]);
} else {
    $ip_info = [];
}

$localization = $sb->getLocalizationClass();

// frontend stuff defined here so we can reuse this between
// trinium and finalium
$buttons = [
    'ban_user' => [
        'condition' => $user['powerlevel'] <= 1,
        'states' => [
            'banned' => [
                'condition' => $user['is_banned'],
                'name' => 'ban_user',
                'value' => $user['name'],
                'class' => 'button warning',
                'label' => 'Unban',
                'confirm' => 'Are you sure you want to unban this user?',
            ],
            'not_banned' => [
                'condition' => !$user['is_banned'],
                'name' => 'ban_user',
                'value' => $user['name'],
                'class' => 'button danger',
                'label' => 'Ban',
                'confirm' => 'Are you sure you want to ban this user?',
            ],
        ],
    ],
    'verify_user' => [
        'condition' => $user['powerlevel'] <= 1,
        'states' => [
            'verified' => [
                'condition' => !($flags & UserFlags::FLAG_UNVERIFIED->value),
                'name' => 'verify_user',
                'value' => $user['name'],
                'class' => 'button secondary',
                'label' => 'Unverify',
                'confirm' => 'Are you sure you want to unverify this user?',
            ],
            'unverified' => [
                'condition' => $flags & UserFlags::FLAG_UNVERIFIED->value,
                'name' => 'verify_user',
                'value' => $user['name'],
                'class' => 'button success',
                'label' => 'Verify',
                'confirm' => 'Are you sure you want to verify this user?',
            ],
        ],
    ],
    'feature_user' => [
        'condition' => true,
        'states' => [
            'featured' => [
                'condition' => $flags & UserFlags::FLAG_FEATURED->value,
                'name' => 'feature_user',
                'value' => $user['name'],
                'class' => 'button secondary',
                'label' => 'Unfeature',
                'confirm' => 'Are you sure you want to unfeature this user?',
            ],
            'not_featured' => [
                'condition' => !($flags & UserFlags::FLAG_FEATURED->value),
                'name' => 'feature_user',
                'value' => $user['name'],
                'class' => 'button accent',
                'label' => 'Feature',
                'confirm' => 'Are you sure you want to feature this user?',
            ],
        ],
    ],
];

$user_info_table = [
    'user_id' => [
        'condition' => true,
        'label' => $localization->translate('user_id'),
        'value' => $user['id'],
    ],
    'ip_address' => [
        'condition' => $auth->userHasRole(UserRoleEnum::Moderator),
        'label' => 'IP address',
        'value' => $user['ip'],
    ],
    'email_address' => [
        'condition' => $auth->userHasRole(UserRoleEnum::Moderator),
        'label' => $localization->translate('email_address'),
        'value' => $auth->userHasRole(UserRoleEnum::Administrator) ? $user['email'] : strstr($user['email'], '@'),
    ],
    'username' => [
        'condition' => true,
        'label' => $localization->translate('username'),
        'value' => $user['name'],
    ],
    'profile_name' => [
        'condition' => true,
        'label' => $localization->translate('profile_name'),
        'value' => $user['title'],
    ],
    'user_role' => [
        'condition' => true,
        'label' => $localization->translate('user_role'),
        'value' => match($user['powerlevel']) {
            1 => $localization->translate('user_role_normal'),
            2 => $localization->translate('user_role_moderator'),
            3 => $localization->translate('user_role_administrator'),
            4 => $localization->translate('user_role_owner'),
            default => $localization->translate('user_role_unknown') . ' (' . $user['powerlevel'] . ')',
        },
    ],
    'age_birthdate' => [
        'condition' => !empty($user['birthdate']),
        'label' => $localization->translate('age') . '/' . $localization->translate('birthdate'),
        'value' => Utilities::calculateAge($user['birthdate']) . ' / ' . $localization->formatDate($user['birthdate'], 'long', 'none'),
    ],
    'registered' => [
        'condition' => true,
        'label' => $localization->translate('registered'),
        'value' => $localization->formatDate($user['joined'], 'long', 'medium')
            . (!empty($user['birthdate']) ? '<br>(' . Utilities::calculateAgeFrom($user['birthdate'], $user['joined']) . ' years old)' : '')
            . ($flags & UserFlags::FLAG_FULPTUBE_ACCOUNT->value ? '<br><small>' . $localization->translate('fulptube_account') . '</small>' : ''),
        'raw_html' => true,
    ],
    'last_active' => [
        'condition' => true,
        'label' => $localization->translate('last_active'),
        'value' => $localization->formatDate($user['last_seen'], 'long', 'medium'),
    ],
    'user_link_color' => [
        'condition' => true,
        'label' => $localization->translate('user_link_color'),
        'value' => null,
        'style' => 'background:' . $user['userlink_color'] . ';',
    ],
];

echo $twig->render("dashboard_user_edit.twig", [
    'user' => $user,
    'flags' => $flags_array,
    'users_with_matching_ips' => $users_with_matching_ips,
    'notes' => $notes_proper,
    'old_names' => $old_username_data,
    'ip_info' => $ip_info,
    'buttons' => $buttons,
    'user_info_table' => $user_info_table,
]);
