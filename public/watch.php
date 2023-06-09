<?php

namespace openSB;

// fixme: change video-related variables to more generic variables

require_once dirname(__DIR__) . '/private/class/common.php';

$id = ($_GET['v'] ?? null);
$ip = getUserIpAddr();

// Takedowns have been backported from BettySB.
$takedown = $sql->fetch("SELECT * FROM takedowns t WHERE t.submission = ?", [$id]);

if ($takedown) {
    error('403', "This submission has been taken down. Reason: " . $takedown["reason"]);
}

$videoData = Videos::getVideoData($userfields, $id);

if(!$videoData) {
    error('404', __("This submission cannot be found."));
}

$query = '';
$count = 0;
$commentData = $sql->query("SELECT $userfields c.comment_id, c.id, c.comment, c.author, c.date, c.deleted, (SELECT COUNT(reply_to) FROM comments WHERE reply_to = c.comment_id) AS replycount FROM comments c JOIN users u ON c.author = u.id WHERE c.id = ? ORDER BY c.date DESC", [$id]);

$relatedVideosData = $sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE NOT v.video_id = ? AND v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY RAND() LIMIT 6", [$id]);

// move this to getVideoData
$totalLikes = $sql->result("SELECT COUNT(rating) FROM rating WHERE video=? AND rating=1", [$videoData['id']]);
$totalDislikes = $sql->result("SELECT COUNT(rating) FROM rating WHERE video=? AND rating=0", [$videoData['id']]);
$combinedRatings = $totalDislikes + $totalLikes;

$allRatings = Videos::calculateRatio($totalDislikes, $totalLikes, $combinedRatings);

$totalFavorites = $sql->result("SELECT COUNT(video_id) FROM favorites WHERE video_id=?", [$videoData['video_id']]);

$allVideos = $sql->result("SELECT COUNT(id) FROM videos WHERE author=?", [$videoData['u_id']]);

if (isset($userdata['name'])) {
    if ($sql->result("SELECT * from favorites WHERE video_id = ? AND user_id = ?", [$videoData['video_id'], $userdata['id']])) {
        $isFavorited = true;
    } else {
        $isFavorited = false;
    }
    $rating = $sql->result("SELECT rating FROM rating WHERE video=? AND user=?", [$videoData['id'], $userdata['id']]);
    $subscribed = $sql->result("SELECT COUNT(user) FROM subscriptions WHERE id=? AND user=?", [$userdata['id'], $videoData['author']]);
} else {
    $isFavorited = false;
    $rating = 2;
    $subscribed = 0;
}
if ($sql->fetch("SELECT COUNT(video_id) FROM views WHERE video_id=? AND user=?", [$videoData['video_id'], crypt($ip, $ip)])['COUNT(video_id)'] < 1) {
    $sql->query("INSERT INTO views (video_id, user) VALUES (?,?)",
        [$videoData['video_id'], crypt($ip, $ip)]);
}

$subCount = $sql->fetch("SELECT COUNT(user) FROM subscriptions WHERE user=?", [$videoData['author']])['COUNT(user)'];
$commentCount = $sql->fetch("SELECT COUNT(id) FROM comments WHERE id=?", [$videoData['video_id']])['COUNT(id)'];
$viewCount = $sql->fetch("SELECT COUNT(video_id) FROM views WHERE video_id=?", [$videoData['video_id']])['COUNT(video_id)'];

// scrapped randley layout had dumb code regarding if the video was "modern" (converted to mp4) or "legacy"
// (converted to dash), so we need to do this. actually i could do this in twig but whatever. -grkb 7/10/2022
if ($videoData['post_type'] == 0 or $videoData['post_type'] == 1) {
    $postType = "video";
} elseif ($videoData['post_type'] == 2) {
    $postType = "artwork";
} else {
    $postType = "unknown";
}

$previousRecentView = $sql->result("SELECT most_recent_view from videos WHERE video_id = ?", [$id]);
$currentTime = time();

$sql->query("UPDATE videos SET most_recent_view = ? WHERE video_id = ?", [$currentTime, $id]);

$twig = twigloader();
echo $twig->render('watch.twig', [
    'video' => $videoData,
    'related_videos' => $relatedVideosData,
    'comments' => $commentData,
    'total_likes' => $totalLikes,
    'total_dislikes' => $totalDislikes,
    'total_rating' => $combinedRatings,
    'total_favorites' => $totalFavorites,
    'rating' => $rating,
    'subscribed' => $subscribed,
    'subCount' => $subCount,
    'comCount' => $commentCount,
    'viewCount' => $viewCount,
    'videoRatio' => $allRatings,
    'recentView' => $previousRecentView,
    'allVideos' => $allVideos,
    'postType' => $postType,
    'isFavorited' => $isFavorited,
]);