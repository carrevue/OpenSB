<?php

namespace OpenSB;

global $twig, $database, $orange, $auth;

use SquareBracket\Utilities;
use SquareBracket\UploadQuery;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

if ($options["skin"] == "charla") {
    $type = isset($options["charla_homepage_type"]) && $options["charla_homepage_type"] !== "list" ? $options["charla_homepage_type"] : "list";
} else {
    $type = "list";
}

if ($options["skin"] == "biscuit" || $options["skin"] == "charla") {
    if ($options["skin"] == "charla" && $type == "grid") {
        $submissions_random_query_limit = 12;
    } else {
        $submissions_random_query_limit = 24;
    }
    $submissions_recent_query_limit = 12;
} else {
    $submissions_random_query_limit = 12;
    $submissions_recent_query_limit = 12;
}

// bootstrap frontend did not list random uploads.
if ($options["skin"] != "bootstrap") {
    $submissions_random = $submission_query->query("RAND()", $submissions_random_query_limit);
} else {
    $submissions_random = [];
}

$submissions_recent = $submission_query->query("v.time DESC", $submissions_recent_query_limit);

$news_recent = $database->fetchArray($database->query("SELECT j.* FROM journals j WHERE j.is_site_news = 1 ORDER BY j.date DESC LIMIT 5"));
//$users_recent = $database->fetchArray($database->query("SELECT u.id, u.about, u.title, (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num FROM users u ORDER BY u.lastview DESC LIMIT 8"));

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions_random),
    "submissions_new" => Utilities::makeUploadArray($database, $submissions_recent),
    "news_recent" => Utilities::makeJournalArray($database, $news_recent),
    //"users_recent" => $users_recent, // TODO: makeUsersArray
];

echo $twig->render('index.twig', [
    'data' => $data,
    'type' => $type,
]);
