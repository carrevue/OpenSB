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

namespace OpenSB;

global $twig, $database, $orange;

use SquareBracket\UploadFlags;
use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

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
    $news_recent_query_limit = 5;
}

if ($options["skin"] == "bootstrap") {
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
