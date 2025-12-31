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

namespace OpenSB\Pages;

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;
use OpenSB\UserFlags;
use OpenSB\UserCustomizationData;
use OpenSB\UploadQuery;

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, attempt to fetch the user's current name through their id
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        if ($new_username) {
            Utilities::redirect('/user/' . $new_username, 301);
        } else {
            // if for whatever reason this leads to nowhere (eg: deleted user or 
            // half-assed prod blacklisting), return to homepage.
            Utilities::notifyBanner("notify_invalid_user", "/");
        }
    } else {
        Utilities::notifyBanner("notify_invalid_user", "/");
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

// page-specific shit Here.

$upload_query = new UploadQuery($sb);

function getOrderFromType($type): string
{
    $order = match ($type) {
        'recent' => "v.timestamp DESC",
        'popular' => "views DESC",
        default => "v.timestamp DESC",
    };
    return $order;
}

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order = getOrderFromType($type);
$limit = $database->paginate($page, 20);

$uploads = $upload_query->query($order, $limit, "v.author = ?", [$data["id"]]);
$upload_count = $upload_query->count("v.author = ?", [$data["id"]]);

$page_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["userlink_color"],
    "about" => ($data["about"] ?? null),
    "customization" => $profile_customization_data?->getData() ?? false,
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "count" => $upload_count,
];

if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapFrontendProfileGradient($data["userlink_color"]);
}

echo $twig->render('profile_browse.twig', [
    'data' => $page_data,
    'page' => $page,
    'type' => $type,
]);
