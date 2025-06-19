<?php

namespace OpenSB;

global $twig, $database;

use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

function getOrderFromType($type): string
{
    $order = match ($type) {
        'recent' => "v.time DESC",
        'popular' => "views DESC",
        'random' => "RAND()",
        default => "v.time DESC",
    };
    return $order;
}

$type = ($_GET['type'] ?? 'recent');
$user = ($_GET['user'] ?? null);
$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order = getOrderFromType($type);
$limit = sprintf("%s,%s", (($page_number - 1) * 20), 20);

if ($user) {
    // TODO: handle old names
    $id = Utilities::usernameToUserID($database, $user);
    if (!$id) {
        Utilities::notifyBanner("This user does not exist.", "/");
    }
    $submissions = $submission_query->query($order, $limit, "v.author = ?", [$id]);
    $submission_count = $submission_query->count("v.author = ?", [$id]);
} else {
    $submissions = $submission_query->query($order, $limit);
    $submission_count = $submission_query->count();
}

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions),
    "count" => $submission_count,
];

echo $twig->render('browse.twig', [
    'user' => $user,
    'data' => $data,
    'page' => $page_number,
    'type' => $type,
]);