<?php
namespace OpenSB;

global $database;

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_DYNAMIC_PATH", SB_ROOT_PATH . '/dynamic');
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once SB_PRIVATE_PATH . '/class/common.php';

$uploads = $database->fetchArray($database->query("SELECT * FROM videos"));

foreach ($uploads as $upload) {
    $viewCountsQuery = $database->query("
        SELECT
            COUNT(CASE WHEN type = 'user' THEN 1 END) AS logged_in_views,
            COUNT(CASE WHEN type = 'guest' THEN 1 END) AS logged_out_views
        FROM
            views
        WHERE
            video_id = ?
    ", [$upload["video_id"]]);

    $viewCountsResult = $database->fetchArray($viewCountsQuery);

    $loggedInViews = $viewCountsResult[0]["logged_in_views"];
    $loggedOutViews = $viewCountsResult[0]["logged_out_views"];

    // calculate a ratio as an attempt to estimate human views.
    if ($loggedInViews == 0) {
        $ratio = 4;
    } elseif ($loggedInViews >= 10) {
        $ratio = 2;
    } else {
        $ratio = 4 - (2 * ($loggedInViews - 1) / 8);
    }

    // updated view count
    $adjustedViewCount = $loggedInViews + ($loggedOutViews / $ratio);

    echo "Video ID: " . $upload["video_id"] . " - Adjusted View Count: " . round($adjustedViewCount) . "\n";

    $database->query("UPDATE videos SET views = ? WHERE video_id = ?", [$adjustedViewCount, $upload["video_id"]]);
}
