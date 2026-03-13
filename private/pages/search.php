<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

global $twig, $sb, $database;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Data\Upload\UploadQuery;
use Core\Utilities;

$CrawlerDetect = new CrawlerDetect;

// if it's a crawler, don't bother.
if ($CrawlerDetect->isCrawler()) {
    exit;
}

$upload_query = new UploadQuery($sb);

$query = $_GET['query'] ?? null;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit = $database->paginate($page, pp: 20);

// TODO: searching for a user should prioitize them and their uploads

$uploads = $upload_query->query(
    "(
        (v.tags = ?) * 200 +
        (v.tags LIKE CONCAT('%', ?, '%')) * 120 +
        (v.title = ?) * 60 +
        (v.title LIKE CONCAT('%', ?, '%')) * 30 +
        (v.description LIKE CONCAT('%', ?, '%')) * 10
     )
     + (LOG10(v.views + 1) * 5)
     DESC,
     v.timestamp DESC",
    $limit,
    "(v.tags LIKE CONCAT('%', ?, '%')
      OR v.title LIKE CONCAT('%', ?, '%')
      OR v.description LIKE CONCAT('%', ?, '%'))",
    [
        // scoring
        $query, $query, $query, $query, $query,
        // filtering
        $query, $query, $query
    ]
);

$upload_count = $upload_query->count("(v.tags LIKE CONCAT('%', ?, '%') 
    OR v.title LIKE CONCAT('%', ?, '%') 
    OR v.description LIKE CONCAT('%', ?, '%'))", [$query, $query, $query]);

$data = [
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "count" => $upload_count,
    "query" => $query,
];

echo $twig->render('search.twig', [
    'data' => $data,
    'page' => $page,
]);
