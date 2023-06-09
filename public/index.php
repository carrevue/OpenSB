<?php

namespace openSB;
require_once dirname(__DIR__) . '/private/class/common.php';

// currently selects all uploaded videos, should turn it into all featured only
$videoData = $sql->query("SELECT $userfields $videofields, v.category_id FROM videos v JOIN users u ON v.author = u.id WHERE v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY RAND() LIMIT 16");
// moved total subscribers to layout.php
if ($log) {
    $query = implode(', ', array_column($sql->fetchArray($sql->query("SELECT user FROM subscriptions WHERE id = ?", [$userdata['id']])), 'user'));
    if ($query != null) {
        $subscriptionVideos = $sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE v.author IN($query) WHERE v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY v.id DESC LIMIT 16");
    } else {
        $subscriptionVideos = null;
    }
    $totalViews = $sql->result("SELECT SUM(views) FROM videos WHERE author = ?", [$userdata['id']]); // broken since 2021 (or 2022?) given how views are stored differently. -grkb 4/20/23
    $creationDate = $sql->result("SELECT joined FROM users WHERE id = ?", [$userdata['id']]);
	$totalSubmissions = $sql->result("SELECT COUNT(*) FROM videos WHERE author = ?", [$userdata['id']]);
} else {
    $subscriptionVideos = null;
    $totalViews = 0;
    $creationDate = 0;
	$totalSubmissions = 0;
}
$twig = twigloader();

echo $twig->render('index.twig', [
    'videos' => $videoData,
    'subscriptionVideos' => $subscriptionVideos,
    'totalSubmissions' => $totalSubmissions,
    'creationDate' => $creationDate,
    'updated' => (isset($_GET['updated']) ? true : false),
]);
