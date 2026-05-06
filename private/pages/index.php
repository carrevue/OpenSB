<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou

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

global $twig, $database, $sb, $auth;

use Core\Utilities;
use Data\Upload\UploadFlags;
use Data\Upload\UploadQuery;
use Data\User\UserQuery;
use Data\User\UserFlags;

$options = $sb->getLocalOptions();

// use different index for finalium skin
if ($options["skin"] == "finalium") {
    include_once "index_finalium.php";
    exit;
}

if ($auth->isUserLoggedIn()) {
    $type = isset($options["home_type"]) && $options["home_type"] !== "following" ? $options["home_type"] : "following";
} else {
    $type = "featured";
}

$upload_query = new UploadQuery($sb);

$post_query_limit = 12;
$uploads_featured_query_limit = 3;
$news_recent_query_limit = 1;

$uploads_featured = $upload_query->query(
    "v.timestamp DESC",
    $uploads_featured_query_limit,
    sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
)->toCleanArray();

$featured_users = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.name
        FROM users u 
        WHERE u.flags & ? = ?
        ORDER BY RAND() LIMIT 6",
        [UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value]
    )
);

if (!$auth->isUserLoggedIn() && $featured_users) {
    $users = array_map('intval', array_column($featured_users, 'id'));
    $query = implode(', ', $users);

    $uploads_new = $upload_query->query(
        "v.timestamp DESC",
        $uploads_featured_query_limit,
        sprintf("v.author in (%s)", $query)
    )->toCleanArray();
}

if ($options["skin"] == "trinium" & $auth->isUserLoggedIn()) { // TODO: bootstrap had this too back then
    // copied from SquareBracketTwigExtension
    $rows = $database->fetchArray(
        $database->query(
            "SELECT s.* FROM user_follows s
            JOIN users u ON s.user = u.id
            WHERE s.user = ?
            AND s.id NOT IN (SELECT user FROM user_bans)",
            [$auth->getUserID()]
        )
    );

    if ($rows) {
        $users = array_map('intval', array_column($rows, 'id'));
        $query = implode(', ', $users);

        $uploads_following = $upload_query->query(
            "v.timestamp DESC",
            $uploads_featured_query_limit,
            sprintf("v.author in (%s)", $query)
        )->toCleanArray();
    } else {
        $uploads_following = [];
    }
} else {
    $uploads_following = [];
}

$news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_news = 1 ORDER BY j.timestamp DESC LIMIT $news_recent_query_limit"));

if ($options["skin"] == "trinium") {
    $user_query = new UserQuery($sb);
    $users_recent = $user_query->toArray($user_query->query("u.last_seen DESC", 5, "u_num != 0"));
} else {
    $users_recent = [];
}

$data = [
    "uploads_new" => $uploads_new,
    "uploads_featured" => $uploads_featured,
    "uploads_following" => $uploads_following,
    "news_recent" => Utilities::makeJournalArray($database, $news_recent) ?? [],
    "users_recent" => $users_recent ?? [],
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
