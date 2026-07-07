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
use Data\User\UserData;
use Data\User\UserFlags;
use Data\Playlist\PlaylistData;

$upload_query = new UploadQuery($sb);

/*
$uploads_featured = $upload_query->query(
    "uploaded DESC",
    15,
    sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
)->toCleanArray();
*/

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
                u.id NOT IN (SELECT user FROM user_bans)
            )
            ORDER BY RAND() LIMIT 6",
            [$auth->getUserId()]
        )
    );
}

if (!empty($following_users)) {
    $recommended_users = $following_users;
} else {
    // select users if they're 
    // 1. not shadowbanned
    // 2. are in the top 20 of being most followed or are featured
    // 3. have last logged in the last month (this does not apply to staff)
    // 4. not banned
    $recommended_users = $database->fetchArray(
        $database->query(
            "SELECT u.id, u.name, u.title
            FROM users u
            WHERE u.u_index >= 6
            AND (
                (u.flags & ?) != ?
            )
            AND (
                (u.f_index >= (SELECT MIN(f_index) FROM (SELECT f_index FROM users ORDER BY f_index DESC LIMIT 20) t))
                OR (u.flags & ?) = ?
            )
            AND (
                u.powerlevel != 1 OR u.last_seen > ?
            )
            AND (
                u.id NOT IN (SELECT user FROM user_bans)
            )
            ORDER BY RAND() LIMIT 6",
            [
                UserFlags::FLAG_SHADOW_BAN->value, UserFlags::FLAG_SHADOW_BAN->value,
                UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value,
                strtotime('-1 month')
            ]
        )
    );
}

$localization = $sb->getLocalizationClass();

$feed = [];

if (empty($following_users)) {
    /*
    $feed["featured"] = [
        "icon" => $sb->getCurrentThemeName() === 'hitchhiker'
                ? "/assets/skin/finalium/homepage_featured_hitchhiker.svg"
                : "/assets/skin/finalium/homepage_featured.svg",
        "title" => $localization->translate('featured_on_site', $sb->getBrandingSettings()["name"]),
        "desc" => $localization->translate('featured_uploads_desc'),
        "uploads" => $uploads_featured,
    ];
    */

    // temporary code for testing playlists -chaziz 07/07/2026
    $playlist = new PlaylistData($database, "test");

    if ($playlist->getData() != null) {
        $upload_map = $playlist->getUploads();
        $query = implode(', ', $upload_map);
        
        $playlist_uploads = $upload_query->query(
            sprintf("FIELD(v.id, %s)", implode(",", array_map('intval', $upload_map))),
            20,
            sprintf("v.id in (%s)", $query)
        )->toCleanArray();

        $author = new UserData($database, $playlist->getData()["author"]);

        // temporary shit -chaziz 07/07/2026
        $feed["playlist"] = [
            "icon" => $sb->getStorageClass()->getUserProfilePicture($playlist->getData()["author"]),
            "title" => $playlist->getData()["title"],
            "author" => $author->getUserArray(),
            "desc" => $playlist->getData()["description"],
            "uploads" => $playlist_uploads,
        ];
    }
} else {
    /*$feed["recommended"] = [
        "title" => $localization->translate('recommended'),
        "uploads" => $uploads_featured,
    ];*/
}

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
