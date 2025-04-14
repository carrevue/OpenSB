<?php

namespace OpenSB;

global $auth, $isChazizSB, $twig, $database, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::notifyBanner("Please change your skin to Biscuit.", "/theme");
}

/**
 * Based on the implementation in principia-web. Originally, this was 5 slightly different duplicated functions.
 */
function makeRunningTotalGraph($database, $table, $orderfield): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT $orderfield, num_interactions,
			@runningTotal := @runningTotal + totals.num_interactions AS runningTotal
		FROM
			(SELECT FROM_UNIXTIME($orderfield) AS $orderfield, COUNT(*) AS num_interactions
				FROM $table AS e
				GROUP BY DATE(FROM_UNIXTIME(e.$orderfield))) totals
		ORDER BY $orderfield"));
}

function makeRunningTotalGraphFromMultipleCommentTables($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT date, num_interactions,
            @runningTotal := @runningTotal + num_interactions AS runningTotal
        FROM (
            (SELECT FROM_UNIXTIME(date) AS date, COUNT(*) AS num_interactions
            FROM upload_comments
            GROUP BY DATE(FROM_UNIXTIME(date)))
            UNION ALL
            (SELECT FROM_UNIXTIME(date) AS date, COUNT(*) AS num_interactions
            FROM user_profile_comments
            GROUP BY DATE(FROM_UNIXTIME(date)))
            UNION ALL
            (SELECT FROM_UNIXTIME(date) AS date, COUNT(*) AS num_interactions
            FROM journal_comments
            GROUP BY DATE(FROM_UNIXTIME(date)))
        ) AS combined_data
        ORDER BY date"
    ));
}

function countViews($database): array
{
    return $database->fetchArray($database->query(
        "SELECT 
            DATE(FROM_UNIXTIME(timestamp)) AS date, 
            SUM(CASE WHEN type = 'user' THEN 1 ELSE 0 END) AS user_views,
            SUM(CASE WHEN type = 'guest' THEN 1 ELSE 0 END) AS guest_views
        FROM upload_views
        GROUP BY DATE(FROM_UNIXTIME(timestamp))
        ORDER BY DATE(FROM_UNIXTIME(timestamp))"
    ));
}

$date = $database->fetch("SELECT u.joined FROM users u ORDER BY u.joined ASC")["joined"];

$thingsToCount = [
    'upload_comments' => 'Comments on uploads',
    'user_profile_comments' => 'Comments on profiles',
    'journal_comments' => 'Comments on journals',
    'users' => 'Users',
    'uploads' => 'Uploads',
    'upload_deleted' => 'Deleted uploads',
    'upload_takedowns' => 'Taken down uploads',
    'upload_views' => 'Views',
    'user_favorites' => 'Favorites',
    'user_bans' => 'Bans',
    'journals' => 'Journals'
];

$query = "SELECT ";
$first = true;

foreach ($thingsToCount as $table => $uiName) {
    if (!$first) {
        $query .= ", ";
    }
    $query .= sprintf("(SELECT COUNT(*) FROM %s) AS %s", $table, $table);
    $first = false;
}

$numbersOfThingsArray = $database->fetch($query);

$results = [];
foreach ($thingsToCount as $table => $uiName) {
    $results[] = [
        'name' => $uiName,
        'value' => $numbersOfThingsArray[$table],
        'table' => $table,
    ];
}

// unbanned-to-banned user ratio
$totalUsers = $numbersOfThingsArray['users'];
$bannedUsers = $numbersOfThingsArray['user_bans'];
$unbannedUsers = $totalUsers - $bannedUsers;
$unbannedRatio = ($totalUsers > 0) ? ($unbannedUsers / $totalUsers) * 100 : 0;

$results[] = [
    'name' => "Unbanned user percentage",
    'value' => round($unbannedRatio) . "%",
];

// undeleted-to-deleted upload ratio

// this does not include takedowns yet, why? because, at least in the prod sb db, the table for it reference uploads
// that were later completely deleted off the database. -chaziz -4/14/2025
$undeletedUploads = $numbersOfThingsArray['uploads'];
$deletedUploads = $numbersOfThingsArray['upload_deleted'];

$totalUploads = $undeletedUploads + $deletedUploads;
$undeletedRatio = ($totalUploads > 0) ? ($undeletedUploads / $totalUploads) * 100 : 0;

$results[] = [
    'name' => "Non-deleted upload percentage",
    'value' => round($undeletedRatio) . "%",
];

$is_windows = str_starts_with(php_uname(), "Windows") ?? false;

// get distro info if on a unix-based system that supports os-release
// this is better than using lsb-release because lsb is some dead linux-only standard while os-release will work on
// anything that uses systemd, including freebsd from what ive seen online. openrc may support this but im not sure.
// -chaziz 12/22/2024
if (file_exists('/etc/os-release')) {
    $os_release = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $os_data = [];
    foreach ($os_release as $line) {
        list($key, $value) = explode("=", $line, 2);
        $os_data[$key] = trim($value, '"');
    }

    if (isset($os_data['PRETTY_NAME'])) {
        $os_name = $os_data['PRETTY_NAME'];
    } else {
        $os_name = null;
    }
} else {
    $os_name = null;
}


$data = [
    "numbers" => $results,
    "system" => [
        "uname" => php_uname(),
        "os_name" => $os_name,
        "is_windows" => $is_windows,
    ],
    "graph_data" => [
        "users" => makeRunningTotalGraph($database, 'users', 'joined'),
        "submissions" => makeRunningTotalGraph($database, 'uploads', 'time'),
        "comments" => makeRunningTotalGraphFromMultipleCommentTables($database),
        "journals" => makeRunningTotalGraph($database, 'journals', 'date'),
        "views" => countViews($database),
    ],
    "time" => [
        "formatted_date" => date("F j, Y", $date),
        "relative_days" => round((time() - $date) / 60 / 60 / 24), // we want the total number of days,
        // not a rounded approximation, so relative time isn't gonna work.
    ],
];

echo $twig->render('admin_overview.twig', [
    'data' => $data
]);