<?php

namespace OpenSB;

global $twig, $database;

use SquareBracket\Utilities;

function getOrderFromType($type): string
{
    switch ($type) {
        case 'recent':
            $order = "v.time";
            break;
        case 'popular':
            $order = "views";
            break;
        case 'discussed':
            $order = "comments";
            break;
        case 'favorited':
            $order = "favorites";
            break;
        case 'random':
            $order = "RAND()";
            break;
        default:
            $order = "v.time";
            break;
    }
    return $order;
}

$query = $_GET['query'] ?? null;
$type = ($_GET['type'] ?? 'recent');
$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order = getOrderFromType($type);
$limit = sprintf("LIMIT %s,%s", (($page_number - 1) * 20), 20);

$whereRatings = Utilities::whereRatings();

// TODO: port this to UploadQuery
$submissions = $database->fetchArray(
    $database->query(
        "SELECT v.* FROM uploads v WHERE (v.tags LIKE CONCAT('%', ?, '%')
                                  OR v.title LIKE CONCAT('%', ?, '%') 
                                  OR v.description LIKE CONCAT('%', ?, '%')) 
                                  AND $whereRatings 
                                  AND v.video_id NOT IN (SELECT submission FROM upload_takedowns) 
                                  AND v.author NOT IN (SELECT userid FROM user_bans)
                                  ORDER BY $order DESC $limit",
        [$query, $query, $query]));

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions),
];

echo $twig->render('browse.twig', [
    'data' => $data,
    'page' => $page_number,
]);