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

use OpenSB\PostQuery;
use OpenSB\UploadFlags;
use OpenSB\UploadQuery;
use OpenSB\UserQuery;
use OpenSB\Utilities;

$options = $sb->getLocalOptions();

// use different index for finalium skin
if ($options["skin"] == "finalium") {
    include_once "index_finalium.php";
    exit;
}

$enable_wavelet = $options["skin"] == "trinium" && $sb->isIncompleteFeaturesEnabled();

if ($enable_wavelet) {
    $post_query = new PostQuery($sb);
}

$upload_query = new UploadQuery($sb);
if ($options["skin"] == "trinium") {
    $type = isset($options["trinium_homepage_type"]) && $options["trinium_homepage_type"] !== "list" ? $options["trinium_homepage_type"] : "list";

    if ($type == "wavelet" && !$enable_wavelet) {
        $type = "list";
    }
} else {
    $type = "list"; // avoid undefined warning
}

$uploads_featured_query_limit = 12;
$uploads_recent_query_limit = 12; // only used on bootstrap skin's classic theme
$news_recent_query_limit = 1;

if ($options["skin"] == "bootstrap" && $options["theme"] == "classic") {
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
        $users = array_map('intval', array_column($rows, 'id'));
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

if ($type == "wavelet" && $enable_wavelet) {
    //$posts = $database->fetchArray($database->query("SELECT j.* FROM journals j ORDER BY j.timestamp DESC LIMIT 12"));
    $posts = $post_query->query("p.timestamp", 12);
} else {
    $posts = [];
}

if ($options["skin"] == "trinium") {
    $user_query = new UserQuery($sb);
    $users_recent = Utilities::makeUserArray($database, $user_query->query("u.last_seen DESC", 5, "u_num != 0"));
} else {
    $users_recent = [];
}

$data = [
    "uploads_new" => Utilities::makeUploadArray($database, $uploads_recent) ?? [],
    "uploads_featured" => Utilities::makeUploadArray($database, $uploads_featured) ?? [],
    "uploads_following" => Utilities::makeUploadArray($database, $uploads_following) ?? [],
    "news_recent" => Utilities::makeJournalArray($database, $news_recent) ?? [],
    //"posts" => Utilities::makeJournalArray($database, $posts) ?? [],
    "posts" => $posts,
    "users_recent" => $users_recent ?? [],
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
