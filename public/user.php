<?php

namespace openSB;

require_once dirname(__DIR__) . '/private/class/common.php';

$message = '';

if (isset($_GET['id'])) {
    $userpagedata = $sql->fetch("SELECT $accountfields FROM users WHERE id = ?", [$_GET['id']]);
    if (!isset($userpagedata) || !$userpagedata) {
        error('404', 'The requested user is invalid');
    }
} else if (isset($_GET['name'])) {
    $userpagedata = $sql->fetch("SELECT $accountfields FROM users WHERE name = ?", [$_GET['name']]);
    if (!isset($userpagedata) || !$userpagedata) {
        error('404', 'The requested user is invalid');
    }
} else {
    error('404', 'No user specified');
}

$page = (isset($_GET['p']) && is_numeric($_GET['p']) && $_GET['p'] > 0 ? $_GET['p'] : 1);
$forceuser = isset($_GET['forceuser']);

$limit = sprintf("LIMIT %s,%s", (($page - 1) * $paginationLimit), $paginationLimit);
$latestVideoData = $sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE v.author = ? AND v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY v.id DESC $limit", [$userpagedata['id']]);
$latestVideo = $sql->fetch("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE v.author = ? ORDER BY v.id DESC", [$userpagedata['id']]);
$countVideos = $sql->result("SELECT COUNT(*) FROM videos l WHERE l.author = ? AND `post_type` = 0 OR `post_type` = 1 ", [$userpagedata['id']]);
$countArt = $sql->result("SELECT COUNT(*) FROM videos l WHERE l.author = ? AND `post_type` = 2 ", [$userpagedata['id']]);

$commentData = $sql->query("SELECT $userfields c.comment_id, c.id, c.comment, c.author, c.date, c.deleted, (SELECT COUNT(reply_to) FROM comments WHERE reply_to = c.comment_id) AS replycount FROM channel_comments c JOIN users u ON c.author = u.id WHERE c.id = ? ORDER BY c.date DESC", [$userpagedata['id']]);

$subCount = $sql->fetch("SELECT COUNT(user) FROM subscriptions WHERE user = ?", [$userpagedata['id']])['COUNT(user)'];
$subscribers = $sql->query("SELECT $userfields s.* FROM subscriptions s JOIN users u on user WHERE s.user = ?", [$userpagedata['id']]);
$totalViews = $sql->result("SELECT SUM(views) FROM videos WHERE author = ?", [$userpagedata['id']]);

if ($storage->fileExists('../dynamic/banners/' . $userpagedata["name"] . '.png')) {
    $bannerExists = true;
}

if (isset($log) && !empty($log)) {
    $subscribed = $sql->result("SELECT COUNT(user) FROM subscriptions WHERE id=? AND user=?", [$userdata['id'], $userpagedata['id']]);
} else {
    $subscribed = 0;
}

//fixes depreciation warning
if ($userpagedata['about'] == null) {
    $userpagedata['about'] = '';
}

$twig = twigloader();
echo $twig->render("user.twig", [
    'id' => $userpagedata['id'],
    'name' => $userpagedata['name'],
    'userpagedata' => $userpagedata,
    'latestVideos' => $latestVideoData,
    'video' => $latestVideo,
    'forceuser' => $forceuser,
    'page' => $page,
    'level_count' => $countVideos,
    'art_count' => $countArt,
    'markread' => isset($_GET['markread']),
    'edited' => isset($_GET['edited']),
    'comments' => ($comments ?? null),
    'subCount' => $subCount,
    'subscribed' => $subscribed,
    'comments' => $commentData,
    'message' => $message,
    'subscribers' => $subscribers,
    'views' => $totalViews,
    'bannerExists' => ($bannerExists ?? false),
]);
