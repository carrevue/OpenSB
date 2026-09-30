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

use Core\Utilities;
use Data\User\UserData;
use Data\User\UserFlags;

include_once('_include.php');

use Data\Playlist\Playlist;

//$uploads_featured = get_N_featured_uploads($upload_query, 15);
$uploads_new = get_N_new_uploads($database, $upload_query, 1, 15);

if ($auth->isLoggedIn()) {
    $following_users = $database->fetchArray(
        $database->query(
            "SELECT u.id, u.name, u.title
            FROM users u
            WHERE u.u_index >= 1
            AND (
                u.id IN (SELECT id FROM user_follows WHERE user = ?)
            )
            AND (
                (u.flags & ?) != ?
            )
            AND (
                u.id NOT IN (SELECT user FROM user_bans)
            )
            ORDER BY RAND() LIMIT 6",
            [
                $auth->getUserId(),
                UserFlags::FLAG_SHADOW_BAN->value, UserFlags::FLAG_SHADOW_BAN->value,
            ]
        )
    );
}

if (!empty($following_users)) {
    $recommended_users = $following_users;
} else {
    // select users if they
    // 1. have at least 6 uploads
    // 2. are not shadowbanned (this appears to be broken)
    // 3. are in the top 50 of being most followed or are featured
    // 4. have uploaded something within the last month
    // 5. are not banned
    $recommended_users = get_top_N_users($database, 50, 6, 6);
}

$localization = $sb->getLocalizationClass();

$feed = [];

if (empty($following_users)) {
    $playlist = new Playlist($sb, "test");

    if ($playlist->getData() != null) {
        $feed["playlist"] = [
            "icon" => $sb->getStorageClass()->getUserProfilePicture($playlist->getData()["author"]),
            "title" => $playlist->getData()["title"],
            "author" => $playlist->getAuthorData(),
            "desc" => $playlist->getData()["description"],
            "uploads" => $playlist->getUploads(20),
        ];
    }
} else {
    /*$feed["recommended"] = [
        "title" => $localization->translate('recommended'),
        "uploads" => $uploads_featured,
    ];*/
}

$feed["recent"] = [
    "title" => $localization->translate('homepage_recent_uploads'),
    "label" => $localization->translate('homepage_recent_uploads_desc'),
    "uploads" => $uploads_new,
];

// this feels somewhat inefficient?
foreach ($recommended_users as $user) {
    $feed_key = "user_" . $user["id"];
    $feed[$feed_key] = [
        "icon" => $sb->getStorageClass()->getUserProfilePicture($user["id"]),
        "title" => $user["title"],
        "label" => $auth->isLoggedIn() ? $localization->translate('recommended_member_for_you') : $localization->translate('recommended_member'),
        "link" => "/user/" . $user["name"],
        "uploads" => $upload_query->query(
            "uploaded DESC",
            10,
            sprintf("v.author = %d", $user["id"])
        )->toCleanArray(),
    ];
}

echo $twig->render('index.twig', [
    'feed' => $feed,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
