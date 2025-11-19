<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2022-2025 Chaziz

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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

function get_folder_size($path)
{
    $path = escapeshellarg($path);
    $command = "du -sb $path | cut -f1";
    $size = shell_exec($command);
    return (int)$size;
}

/**
 * Based on the refactored implementation from principia-web. This was 
 * originally 5 slightly different duplicated functions.
 */
function make_running_total_graph($database, $table, $orderfield): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT $orderfield, num_interactions,
			@runningTotal := @runningTotal + totals.num_interactions AS runningTotal
		FROM
			(SELECT FROM_UNIXTIME($orderfield) AS $orderfield, COUNT(*) AS num_interactions
				FROM $table AS e
				GROUP BY DATE(FROM_UNIXTIME(e.$orderfield))) totals
		ORDER BY $orderfield"
    ));
}

function make_running_total_graph_from_comment_tables($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT timestamp, num_interactions,
            @runningTotal := @runningTotal + num_interactions AS runningTotal
        FROM (
            (SELECT FROM_UNIXTIME(timestamp) AS timestamp, COUNT(*) AS num_interactions
            FROM upload_comments
            GROUP BY DATE(FROM_UNIXTIME(timestamp)))
            UNION ALL
            (SELECT FROM_UNIXTIME(timestamp) AS timestamp, COUNT(*) AS num_interactions
            FROM user_profile_comments
            GROUP BY DATE(FROM_UNIXTIME(timestamp)))
            UNION ALL
            (SELECT FROM_UNIXTIME(timestamp) AS timestamp, COUNT(*) AS num_interactions
            FROM journal_comments
            GROUP BY DATE(FROM_UNIXTIME(timestamp)))
        ) AS combined_data
        ORDER BY timestamp"
    ));
}

function make_running_total_graph_from_views($database): array
{
    return $database->fetchArray($database->query(
        "SELECT 
            DATE(FROM_UNIXTIME(timestamp)) AS timestamp, 
            SUM(CASE WHEN type = 'user' THEN 1 ELSE 0 END) AS user_views,
            SUM(CASE WHEN type = 'guest' THEN 1 ELSE 0 END) AS guest_views
        FROM upload_views
        GROUP BY DATE(FROM_UNIXTIME(timestamp))
        ORDER BY DATE(FROM_UNIXTIME(timestamp))"
    ));
}

$date = $database->fetch("SELECT u.joined FROM users u ORDER BY u.joined ASC")["joined"];

$thingsToCount = [
    'upload_comments' => 'Upload comments',
    'user_profile_comments' => 'Profile comments',
    'journal_comments' => 'Journal comments',
    'users' => 'Users',
    'uploads' => 'Uploads',
    'upload_deleted' => 'Deleted uploads',
    'upload_takedowns' => 'Taken down uploads',
    'upload_views' => 'Views',
    'user_favorites' => 'Favorites',
    'user_bans' => 'User bans',
    'ip_bans' => 'IP bans',
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

$user_graph = make_running_total_graph($database, 'users', 'joined');
$upload_graph = make_running_total_graph($database, 'uploads', 'timestamp');
$comment_graph = make_running_total_graph_from_comment_tables($database);
$journal_graph = make_running_total_graph($database, 'journals', 'timestamp');
$view_graph = make_running_total_graph_from_views($database);

// chart.js data
$chartData = [
    'type' => 'line',
    'data' => [
        'datasets' => [
            [
                'label' => 'Uploads',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['timestamp'],
                        'y' => $graph['runningTotal'],
                    ];
                }, $upload_graph),
                'borderWidth' => 1,
                'yAxisID' => 'n',
            ],
            [
                'label' => 'Users',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['joined'],
                        'y' => $graph['runningTotal'],
                    ];
                }, $user_graph),
                'borderWidth' => 1,
                'yAxisID' => 'n',
            ],
            [
                'label' => 'Comments',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['timestamp'],
                        'y' => $graph['runningTotal'],
                    ];
                }, $comment_graph),
                'borderWidth' => 1,
                'yAxisID' => 'n',
            ],
            [
                'label' => 'Journals',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['timestamp'],
                        'y' => $graph['runningTotal'],
                    ];
                }, $journal_graph),
                'borderWidth' => 1,
                'yAxisID' => 'n',
            ],
            [
                'label' => 'Views (Users)',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['timestamp'],
                        'y' => $graph['user_views'],
                    ];
                }, $view_graph),
                'borderWidth' => 1,
                'yAxisID' => 'v',
            ],
            [
                'label' => 'Views (Guests)',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['timestamp'],
                        'y' => $graph['guest_views'],
                    ];
                }, $view_graph),
                'borderWidth' => 1,
                'yAxisID' => 'v',
            ],
        ]
    ],
    'options' => [
        'elements' => [
            'point' => [
                'radius' => 2
            ]
        ],
        'plugins' => [
            'zoom' => [
                'zoom' => [
                    'drag' => [
                        'enabled' => true,
                    ],
                    'mode' => 'x',
                    'speed' => 100,
                ],
                'pan' => [
                    'enabled' => true,
                    'mode' => 'x',
                    'speed' => 0.5,
                ],
            ],
        ],
        'scales' => [
            'x' => $sb->isChazizSquareBracketInstance()
                ? [
                    'type' => 'time',
                    'min' => '2021-01-30T23:33:29-05:00', // sb was first launched shortly before 01/31/2021
                ]
                : [
                    'type' => 'time',
                ],
            'y' => [
                'beginAtZero' => true
            ],
            'n' => [
                'type' => 'linear',
                'display' => true,
                'position' => 'left',
            ],
            'v' => [
                'type' => 'linear',
                'display' => true,
                'position' => 'right',
            ]
        ],
    ]
];

// unbanned user percentage
$totalUsers = $numbersOfThingsArray['users'];
$bannedUsers = $numbersOfThingsArray['user_bans'];
$unbannedUsers = $totalUsers - $bannedUsers;
$unbannedRatio = Utilities::calculatePercentage(1, $unbannedUsers, $totalUsers);

$results[] = [
    'name' => "Unbanned user percentage",
    'value' => $unbannedRatio,
];

// existing upload percentage
$existingUploads = $numbersOfThingsArray['uploads'];
$unavailableUploads = $numbersOfThingsArray['upload_deleted'] + $numbersOfThingsArray['upload_takedowns'];

var_dump($unavailableUploads);

$totalUploads = $existingUploads + $unavailableUploads;
$existingRatio = Utilities::calculatePercentage(1, $existingUploads, $totalUploads);

$results[] = [
    'name' => "Existing upload percentage",
    'value' => $existingRatio,
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

    $os_name = $os_data['PRETTY_NAME'] ?? null;
} else {
    $os_name = null;
}

// we dont really support windows hosts, because most people on windows would just attempt hosting opensb using xampp as
// a basis which hasnt been updated since november 2023 and is a big pile of shit. also, theres no reliably fast method
// of getting the uptime of a windows system through php without relying on systemfo which is slow as shit or possibly
// fucking around with winmgmts through the unholy com php class. i didnt even know it was possible to interface with
// windows' ole api via php, what the fuck??? -chaziz 4/15/2025
if (!$is_windows) {
    $uptime = shell_exec('uptime -p'); // posix_times() is unreliable
    if ($uptime) {
        $uptime = ltrim($uptime, "up ");
    }

    $avg = sys_getloadavg();

    $root = '/';
    $disk_total = disk_total_space($root);
    $disk_free = disk_free_space($root);
    $disk_used = $disk_total - $disk_free;
    $disk_percentage = Utilities::calculatePercentage(1, $disk_used, $disk_total);

    $instance_size = get_folder_size(BLUFF_ROOT_PATH);

    $disk = [
        "total" => Utilities::formatBytes($disk_total, 2),
        "free" => Utilities::formatBytes($disk_free, 2),
        "used" => Utilities::formatBytes($disk_used, 2),
        "percentage" => $disk_percentage,
        "instance_size" => Utilities::formatBytes($instance_size),
    ];
} else {
    $uptime = "Unknown";
    $avg = [];
    $disk = [];
}

$data = [
    "numbers" => $results,
    "system" => [
        "uname" => php_uname(),
        "os_name" => $os_name,
        "uptime" => $uptime,
        "avg" => $avg,
        "is_windows" => $is_windows,
        "disk" => $disk,
    ],
    "graph_data" => $chartData,
    "time" => [
        "formatted_date" => date("F j, Y", $date),
        "relative_days" => round((time() - $date) / 60 / 60 / 24), // we want the total number of days,
        // not a rounded approximation, so relative time isn't gonna work.
    ],
];

echo $twig->render("dashboard_overview.twig", [
    'data' => $data
]);
