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
use SquareBracket\Utilities;
use SquareBracket\UserRoleEnum;
use SquareBracket\UserFlags;
use SquareBracket\UserCustomizationData;
use SquareBracket\UploadQuery;

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /user/$new_username/uploads");
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

$submission_query = new UploadQuery($database);

function getOrderFromType($type): string
{
    $order = match ($type) {
        'recent' => "v.time DESC",
        'popular' => "views DESC",
        default => "v.time DESC",
    };
    return $order;
}

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order = getOrderFromType($type);
$limit = $database->paginate($page, 20);

$submissions = $submission_query->query($order, $limit, "v.author = ?", [$data["id"]]);
$submission_count = $submission_query->count("v.author = ?", [$data["id"]]);

$data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["customcolor"],
    "about" => ($data["about"] ?? null),
    "customization" => $profile_customization_data?->getData() ?? false,
    "submissions" => Utilities::makeUploadArray($database, $submissions),
    "count" => $submission_count,
];

echo $twig->render('profile_browse.twig', [
    'data' => $data,
    'page' => $page,
    'type' => $type,
]);
