<?php

namespace OpenSB;

global $twig, $database, $orange;

use SquareBracket\UploadFlags;
use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

if ($options["skin"] == "trinium") {
    $type = isset($options["trinium_homepage_type"]) && $options["trinium_homepage_type"] !== "list" ? $options["trinium_homepage_type"] : "list";
} else {
    $type = "list";
}

if ($options["skin"] == "biscuit" || $options["skin"] == "trinium") {
    if ($options["skin"] == "trinium" && $type == "grid") {
        $submissions_random_query_limit = 12;
    } else {
        $submissions_random_query_limit = 24;
    }
    $submissions_recent_query_limit = 12;
} else {
    $submissions_random_query_limit = 12;
    $submissions_recent_query_limit = 12;
}

$submissions_featured_query_limit = 3;

if ($options["skin"] == "bootstrap") {
    // bootstrap frontend did not list random uploads.
    $submissions_random = [];
    $news_recent_query_limit = 1;
} else {
    $submissions_random = $submission_query->query("RAND()", $submissions_random_query_limit);
    $news_recent_query_limit = 5;
}

$submissions_recent = $submission_query->query("v.time DESC", $submissions_recent_query_limit);

$featured_flag_bullshit = UploadFlags::FLAG_FEATURED->value; // looks like shit -chaziz 1/3/2025
$submissions_featured = $submission_query->query("v.time DESC", $submissions_featured_query_limit,
    "v.flags & $featured_flag_bullshit = $featured_flag_bullshit");

$news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_site_news = 1 ORDER BY j.date DESC LIMIT $news_recent_query_limit"));
//$users_recent = $database->fetchArray($database->query("SELECT u.id, u.about, u.title, (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num FROM users u ORDER BY u.lastview DESC LIMIT 8"));

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions_random),
    "submissions_new" => Utilities::makeUploadArray($database, $submissions_recent),
    "submissions_featured" => Utilities::makeUploadArray($database, $submissions_featured),
    "news_recent" => Utilities::makeJournalArray($database, $news_recent),
    //"users_recent" => $users_recent, // TODO: makeUsersArray
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
]);
