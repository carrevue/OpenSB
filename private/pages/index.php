<?php

namespace OpenSB;

global $twig, $database, $orange;

use SquareBracket\UploadFlags;
use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

$enable_new_trinium_feed = isset($options["trinium_new_shit"]) && $options["trinium_new_shit"] == "true";

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
    $news_recent_query_limit = 5;
}

if ($options["skin"] == "bootstrap" || ($options["skin"] == "trinium" && $enable_new_trinium_feed && $type != "grid")) {
    $submissions_random = [];
} else {
    $submissions_random = $submission_query->query("RAND()", $submissions_random_query_limit);
}

$submissions_recent = $submission_query->query("v.time DESC", $submissions_recent_query_limit);

$featured_flag_bullshit = UploadFlags::FLAG_FEATURED->value; // looks like shit -chaziz 1/3/2025
if ($options["skin"] == "trinium" && !$enable_new_trinium_feed) {
    $submissions_featured = $submission_query->query("v.time DESC", $submissions_featured_query_limit,
        "v.flags & $featured_flag_bullshit = $featured_flag_bullshit");
} else {
    $submissions_featured = [];
}

if ($options["skin"] == "trinium" && $enable_new_trinium_feed && $type != "grid") {
    $news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j ORDER BY j.date DESC LIMIT $news_recent_query_limit"));
} else {
    $news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_site_news = 1 ORDER BY j.date DESC LIMIT $news_recent_query_limit"));
}

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions_random),
    "submissions_new" => Utilities::makeUploadArray($database, $submissions_recent),
    "submissions_featured" => Utilities::makeUploadArray($database, $submissions_featured),
    "news_recent" => Utilities::makeJournalArray($database, $news_recent),
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
]);
