<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

global $twig, $database, $sb;

use Data\Upload\UploadFlags;
use Data\Upload\UploadQuery;
use Core\Utilities;
use Data\User\UserFlags;

$is_hitchhiker = $sb->isHitchhiker();

$upload_query = new UploadQuery($sb);

$uploads_featured = $upload_query->query(
    "v.timestamp DESC",
    15,
    sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
)->toCleanArray();

// select users if they're 
// 1. featured
// 2. not shadowbanned
// 3. are in the top 20 of being most followed
// 4. have last logged in the last month (this does not apply to staff)
// 5. are not banned
$recommended_users = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.name
        FROM users u
        WHERE u.u_index >= 6
        AND (
            (u.flags & ?) = ?
        )
        AND (
            (u.flags & ?) != ?
        )
        AND (
            (u.f_index >= (SELECT MIN(f_index) FROM (SELECT f_index FROM users ORDER BY f_index DESC LIMIT 20) t))
        )
        AND (
            u.powerlevel != 1 OR u.last_seen > ?
        )
        AND (
            u.id NOT IN (SELECT user FROM user_bans)
        )
        ORDER BY RAND() LIMIT 6",
        [
            UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value, 
            UserFlags::FLAG_SHADOW_BAN->value, UserFlags::FLAG_SHADOW_BAN->value, 
            strtotime('-1 month')
        ]
    )
);

$localization = $sb->getLocalizationClass();

$feed = [
    "featured" => [
        "icon" => $is_hitchhiker
                ? "/assets/skin/finalium/homepage_featured_hitchhiker.svg"
                : "/assets/skin/finalium/homepage_featured.svg",
        "title" => $localization->translate('featured_on_site', $sb->getBrandingSettings()["name"]),
        "label" => $localization->translate('featured_uploads_desc'),
        "uploads" => $uploads_featured,
    ],
];

// this feels somewhat inefficient?
foreach ($recommended_users as $user) {
    $feed_key = "user_" . $user["id"];
    $feed[$feed_key] = [
        "icon" => $sb->getStorageClass()->getUserProfilePicture($user["id"]),
        "title" => $user["name"],
        "label" => $localization->translate('recommended_member'),
        "link" => "/user/" . $user["name"],
        "uploads" => $upload_query->query(
            "RAND()",
            10,
            sprintf("v.author = %d", $user["id"])
        )->toCleanArray(),
    ];
}

echo $twig->render('index.twig', [
    'feed' => $feed,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
