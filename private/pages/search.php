<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
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

global $twig, $database;

use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$query = $_GET['query'] ?? null;
$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit = sprintf("%s,%s", (($page_number - 1) * 20), 20);

$submissions = $submission_query->query(
    "v.time DESC",
    $limit,
    "(v.tags LIKE CONCAT('%', ?, '%') 
    OR v.title LIKE CONCAT('%', ?, '%') 
    OR v.description LIKE CONCAT('%', ?, '%'))",
    [$query, $query, $query]
);
$submission_count = $submission_query->count("(v.tags LIKE CONCAT('%', ?, '%') 
    OR v.title LIKE CONCAT('%', ?, '%') 
    OR v.description LIKE CONCAT('%', ?, '%'))", [$query, $query, $query]);

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions),
    "count" => $submission_count,
    "query" => $query,
];

echo $twig->render('search.twig', [
    'data' => $data,
    'page' => $page_number,
]);
