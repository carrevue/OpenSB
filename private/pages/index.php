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

namespace OpenSB\Pages;

global $twig, $database, $sb, $auth;

use OpenSB\UploadFlags;
use OpenSB\UploadQuery;
use OpenSB\Utilities;
use OpenSB\UserData;
use OpenSB\UserFlags;

// this page is fucked up and should be cleaned up in 2.1

$upload_query = new UploadQuery($sb);

$options = $sb->getLocalOptions();

// on finalium, use new fulptube index page.
if ($options["skin"] == "finalium") {
    include_once "index_new.php";
    die();
}

$uploads_recent_query_limit = 12; // only used on bootstrap skin's classic theme

if ($options["skin"] == "trinium") {
    $type = isset($options["trinium_homepage_type"]) && $options["trinium_homepage_type"] !== "list" ? $options["trinium_homepage_type"] : "list";

    if ($type == "grid") {
        $uploads_random_query_limit = 8;
        $uploads_featured_query_limit = 8;
    } else {
        $uploads_random_query_limit = 24;
        $uploads_featured_query_limit = 12;
    }
} else {
    $type = "list";

    $uploads_random_query_limit = 12;
    $uploads_featured_query_limit = 12;
}

if ($options["skin"] == "bootstrap") {
    $news_recent_query_limit = 1;
} else {
    $news_recent_query_limit = 3;
}

if ($options["skin"] == "bootstrap" || ($options["skin"] == "trinium" & $type == "list") || $options["skin"] == "finalium") {
    $uploads_random = [];
} else {
    $uploads_random = $upload_query->query("RAND()", $uploads_random_query_limit);
}

if ($options["skin"] == "bootstrap" & $options["theme"] == "classic") {
    $uploads_recent = $upload_query->query("v.timestamp DESC", $uploads_recent_query_limit);
} else {
    $uploads_recent = [];
}

$uploads_featured = $upload_query->query(
    "v.timestamp DESC",
    $uploads_featured_query_limit,
    sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
);

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
        $users = array_column($rows, 'id');
        $query = implode(', ', $users);

        $uploads_following = $upload_query->query(
            "v.timestamp DESC",
            $uploads_featured_query_limit,
            sprintf("v.author in (%s)", $query)
        );
    } else {
        $uploads_following = [];
    }
} else {
    $uploads_following = [];
}

$news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_news = 1 ORDER BY j.timestamp DESC LIMIT $news_recent_query_limit"));

if ($options["skin"] == "trinium") {
    // TODO: maybe move this (and the equivalent code in users.php) into a "UsersQuery" class?
    $users_recent_data = $database->fetchArray(
        $database->query(
            "SELECT u.id, 
        (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
        (SELECT COUNT(user) FROM user_follows WHERE id = u.id) AS f_num
            FROM users u 
            WHERE u.id NOT IN (SELECT user FROM user_bans)
            AND (u.flags & ?) = 0
            ORDER BY u.last_seen DESC LIMIT 5", [UserFlags::FLAG_UNVERIFIED->value]
        )
    );

    $users_recent = [];
    foreach ($users_recent_data as $user) {
        $userData = new UserData($database, $user["id"]);
        $users_recent[] =
            [
                "id" => $user["id"],
                "info" => $userData->getUserArray(),
                "uploads" => $user["s_num"],
                //"journals" => $user["j_num"],
                "followers" => $user["f_num"],
                //"about" => $user["about"],
            ];
    }
} else {
    $users_recent = [];
}

$data = [
    "uploads" => Utilities::makeUploadArray($database, $uploads_random),
    "uploads_new" => Utilities::makeUploadArray($database, $uploads_recent),
    "uploads_featured" => Utilities::makeUploadArray($database, $uploads_featured),
    "uploads_following" => Utilities::makeUploadArray($database, $uploads_following),
    "news_recent" => Utilities::makeJournalArray($database, $news_recent),
    "users_recent" => $users_recent,
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
