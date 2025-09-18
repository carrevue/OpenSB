<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
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

global $twig, $database, $sb;

use OpenSB\UploadFlags;
use OpenSB\UploadQuery;
use OpenSB\Utilities;
use OpenSB\UserData;

$submission_query = new UploadQuery($database);

$options = $sb->getLocalOptions();

$trinium_new_shit = isset($options["trinium_new_shit"]) && $options["trinium_new_shit"] == "true";

if ($options["skin"] == "trinium") {
    $type = isset($options["trinium_homepage_type"]) && $options["trinium_homepage_type"] !== "list" ? $options["trinium_homepage_type"] : "list";

    if ($type == "grid") {
        $submissions_random_query_limit = 12;
    } else {
        $submissions_random_query_limit = 24;
    }
    $submissions_recent_query_limit = 12;
} else {
    $type = "list";

    $submissions_random_query_limit = 12;
    $submissions_recent_query_limit = 12;
}

$submissions_featured_query_limit = 4;

if ($options["skin"] == "bootstrap") {
    $news_recent_query_limit = 1;
} else {
    $news_recent_query_limit = 3;
}

if ($options["skin"] == "bootstrap" || ($options["skin"] == "trinium" & $type == "list")) {
    $submissions_random = [];
} else {
    $submissions_random = $submission_query->query("RAND()", $submissions_random_query_limit);
}

$submissions_recent = $submission_query->query("v.time DESC", $submissions_recent_query_limit);

$featured_flag_bullshit = UploadFlags::FLAG_FEATURED->value; // looks like shit -chaziz 1/3/2025

$submissions_featured = $submission_query->query(
    "v.time DESC",
    $submissions_featured_query_limit,
    "v.flags & $featured_flag_bullshit = $featured_flag_bullshit"
);

$news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_site_news = 1 ORDER BY j.date DESC LIMIT $news_recent_query_limit"));

if ($options["skin"] == "trinium") {
    // TODO: maybe move this (and the equivalent code in users.php) into a "UsersQuery" class?
    $users_recent_data = $database->fetchArray(
        $database->query(
            "SELECT u.id, 
        (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
        (SELECT COUNT(user) FROM user_follows WHERE id = u.id) AS f_num
            FROM users u 
            WHERE u.id NOT IN (SELECT userid FROM user_bans)
            ORDER BY u.lastview DESC LIMIT 5"
        )
    );

    $users_recent = [];
    foreach ($users_recent_data as $user) {
        $userData = new UserData($database, $user["id"]);
        $users_recent[] =
            [
                "id" => $user["id"],
                "info" => $userData->getUserArray(),
                "submissions" => $user["s_num"],
                //"journals" => $user["j_num"],
                "followers" => $user["f_num"],
                //"about" => $user["about"],
            ];
    }
} else {
    $users_recent = [];
}

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions_random),
    "submissions_new" => Utilities::makeUploadArray($database, $submissions_recent),
    "submissions_featured" => Utilities::makeUploadArray($database, $submissions_featured),
    "news_recent" => Utilities::makeJournalArray($database, $news_recent),
    "users_recent" => $users_recent,
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
]);
