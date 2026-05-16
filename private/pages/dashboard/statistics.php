<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2022-2026 Chaziz

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

namespace Pages;

global $auth, $twig, $database, $sb;

use Core\Utilities;
use Data\User\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getCurrentSkinName() != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}

function make_running_total_graph_from_accounts($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT date AS registered, num_interactions,
                @runningTotal := @runningTotal + num_interactions AS runningTotal
         FROM (
             SELECT date, SUM(num_interactions) AS num_interactions
             FROM (
                 SELECT DATE(FROM_UNIXTIME(registered)) AS date, COUNT(*) AS num_interactions
                 FROM accounts
                 GROUP BY DATE(FROM_UNIXTIME(registered))
             ) AS all_events
             GROUP BY date
         ) AS totals
         ORDER BY date"
    ));
}

function make_running_total_graph_from_users($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT date AS joined, num_interactions,
                @runningTotal := @runningTotal + num_interactions AS runningTotal
         FROM (
             SELECT date, SUM(num_interactions) AS num_interactions
             FROM (
                 SELECT DATE(FROM_UNIXTIME(joined)) AS date, COUNT(*) AS num_interactions
                 FROM users
                 GROUP BY DATE(FROM_UNIXTIME(joined))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS date, -COUNT(*) AS num_interactions
                 FROM user_bans
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))
             ) AS all_events
             GROUP BY date
         ) AS totals
         ORDER BY date"
    ));
}

function make_running_total_graph_from_uploads($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT timestamp, num_interactions,
                @runningTotal := @runningTotal + num_interactions AS runningTotal
         FROM (
             SELECT timestamp, SUM(num_interactions) AS num_interactions
             FROM (
                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS timestamp, COUNT(*) AS num_interactions
                 FROM uploads
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(uploaded_time)) AS timestamp, COUNT(*) AS num_interactions
                 FROM upload_deleted
                 GROUP BY DATE(FROM_UNIXTIME(uploaded_time))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(deleted_time)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM upload_deleted
                 GROUP BY DATE(FROM_UNIXTIME(deleted_time))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ut.time)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM upload_takedowns ut
                 INNER JOIN uploads u ON ut.upload = u.upload_id
                 GROUP BY DATE(FROM_UNIXTIME(ut.time))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ub.timestamp)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM uploads u
                 INNER JOIN user_bans ub ON u.author = ub.user
                 GROUP BY DATE(FROM_UNIXTIME(ub.timestamp))
             ) AS all_events
             GROUP BY timestamp
         ) AS combined_data
         ORDER BY timestamp"
    ));
}

function make_running_total_graph_from_journals($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT timestamp, num_interactions,
                @runningTotal := @runningTotal + num_interactions AS runningTotal
         FROM (
             SELECT timestamp, SUM(num_interactions) AS num_interactions
             FROM (
                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS timestamp, COUNT(*) AS num_interactions
                 FROM journals
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ub.timestamp)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM journals j
                 INNER JOIN user_bans ub ON j.author = ub.user
                 GROUP BY DATE(FROM_UNIXTIME(ub.timestamp))
             ) AS all_events
             GROUP BY timestamp
         ) AS combined_data
         ORDER BY timestamp"
    ));
}

function make_running_total_graph_from_comments($database): array
{
    $database->query("SET @runningTotal = 0;");
    return $database->fetchArray($database->query(
        "SELECT timestamp, num_interactions,
                @runningTotal := @runningTotal + num_interactions AS runningTotal
         FROM (
             SELECT timestamp, SUM(num_interactions) AS num_interactions
             FROM (
                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS timestamp, COUNT(*) AS num_interactions
                 FROM upload_comments
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS timestamp, COUNT(*) AS num_interactions
                 FROM user_profile_comments
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(timestamp)) AS timestamp, COUNT(*) AS num_interactions
                 FROM journal_comments
                 GROUP BY DATE(FROM_UNIXTIME(timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ub.timestamp)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM upload_comments c
                 INNER JOIN user_bans ub ON c.author = ub.user
                 GROUP BY DATE(FROM_UNIXTIME(ub.timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ub.timestamp)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM user_profile_comments c
                 INNER JOIN user_bans ub ON c.author = ub.user
                 GROUP BY DATE(FROM_UNIXTIME(ub.timestamp))

                 UNION ALL

                 SELECT DATE(FROM_UNIXTIME(ub.timestamp)) AS timestamp, -COUNT(*) AS num_interactions
                 FROM journal_comments c
                 INNER JOIN user_bans ub ON c.author = ub.user
                 GROUP BY DATE(FROM_UNIXTIME(ub.timestamp))
             ) AS all_events
             GROUP BY timestamp
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

if ($sb->isChazizInstance()) {
    $date = 1612069200;
} else {
    $date = $database->result("SELECT u.joined FROM users u ORDER BY u.joined ASC");
}

$thingsToCount = [
    'accounts' => 'Accounts',
    'users' => 'Users',
    'uploads' => 'Uploads',
    'journals' => 'Journals',
    'upload_comments' => 'Upload comments',
    'user_profile_comments' => 'Profile comments',
    'journal_comments' => 'Journal comments',
    'upload_deleted' => 'Deleted uploads',
    'upload_takedowns' => 'Taken down uploads',
    'upload_views' => 'Views',
    'user_bans' => 'User bans',
    'ip_bans' => 'IP bans',
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

$account_graph = make_running_total_graph_from_accounts($database);
$user_graph = make_running_total_graph_from_users($database);
$upload_graph = make_running_total_graph_from_uploads($database);
$comment_graph = make_running_total_graph_from_comments($database);
$journal_graph = make_running_total_graph_from_journals($database);
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
                'label' => 'Accounts',
                'data' => array_map(function ($graph) {
                    return [
                        'x' => $graph['registered'],
                        'y' => $graph['runningTotal'],
                    ];
                }, $account_graph),
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
                'hidden' => true, // hide this by default
            ],
        ]
    ],
    'options' => [
        'time' => [
            'unit' => 'day',
            'tooltipFormat' => 'MMMM d, yyyy',
        ],
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
            'x' => $sb->isChazizInstance()
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
$unbannedRatio = Utilities::calculatePercentage($unbannedUsers, $totalUsers);

$results[] = [
    'name' => "Unbanned user percentage",
    'value' => $unbannedRatio,
];

// existing upload percentage

// TODO: should be refactored in opensb 2.1, this feels too ugly. -chaziz 11/19/2025
$uploadsByBannedAuthors = $database->result("SELECT COUNT(*) FROM `uploads`
WHERE author IN (SELECT user FROM user_bans)
AND upload_id NOT IN (SELECT upload FROM upload_takedowns)");

$existingUploads = $numbersOfThingsArray['uploads'];
$unavailableUploads = $numbersOfThingsArray['upload_deleted'] + $numbersOfThingsArray['upload_takedowns'] + $uploadsByBannedAuthors;

$totalUploads = $existingUploads + $unavailableUploads;
$existingRatio = Utilities::calculatePercentage($existingUploads, $totalUploads);

$results[] = [
    'name' => "Existing upload percentage",
    'value' => $existingRatio,
];

$data = [
    "numbers" => $results,
    "graph_data" => $chartData,
    "time" => [
        "formatted_date" => date("F j, Y", $date),
        "relative_days" => round((time() - $date) / 60 / 60 / 24), // we want the total number of days,
        // not a rounded approximation, so relative time isn't gonna work.
    ],
];

echo $twig->render("dashboard_statistics.twig", [
    'data' => $data
]);
