<?php

namespace OpenSB;

global $twig, $database;

use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$query = $_GET['query'] ?? null;
$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit = sprintf("%s,%s", (($page_number - 1) * 20), 20);

$submissions = $submission_query->query("v.time DESC", $limit,
    "(v.tags LIKE CONCAT('%', ?, '%') 
    OR v.title LIKE CONCAT('%', ?, '%') 
    OR v.description LIKE CONCAT('%', ?, '%'))", [$query, $query, $query]);
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