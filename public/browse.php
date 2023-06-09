<?php

namespace openSB;

require_once dirname(__DIR__) . '/private/class/common.php';

function get_the_sort_type_shit($type)
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

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order_by = get_the_sort_type_shit($type);
$limit = sprintf("LIMIT %s,%s", (($page - 1) * $paginationLimit), $paginationLimit);
$videoData = $sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY $order_by DESC $limit");
$count = $sql->result("SELECT COUNT(*) FROM videos WHERE videos.video_id NOT IN (SELECT submission FROM takedowns)");

$twig = twigloader();
echo $twig->render('browse.twig', [
    'type' => $type,
    'levels' => $sql->fetchArray($videoData),
    'page' => $page,
    'level_count' => $count
]);