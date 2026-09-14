<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace OpenSB\Pages\Debug;

use Core\Utilities;

global $database;

$data = [];

function get_user_history_data($database, $user): array
{
    $user = Utilities::usernameToUserID($database, $user);
    return $database->fetchArray($database->query(
        "SELECT date, followers, uploads, banned
         FROM user_number_history
         WHERE user = ?
         ORDER BY date",
        [$user]
    ));
}

function get_upload_history_data($database, $upload): array
{
    return $database->fetchArray($database->query(
        "SELECT date, views, views_raw
         FROM upload_number_history
         WHERE upload = ?
         ORDER BY date",
        [$upload]
    ));
}

$username = trim($_POST["username"] ?? "");
$upload = trim($_POST["upload"] ?? "");

if ($username && $upload) {
    die("you can't have both.");
}

if ($username) {
    $data = get_user_history_data($database, $username);
} elseif ($upload) {
    $data = get_upload_history_data($database, $upload);
}

$fields = array_keys($data[0] ?? []);
$fields = array_diff($fields, ['date']);

$axisIds = ['n', 'v'];
$datasets = [];
foreach (array_values($fields) as $i => $field) {
    $datasets[] = [
        'label' => $field,
        'data' => array_map(fn($row) => ['x' => $row['date'], 'y' => $row[$field]], $data),
        'borderWidth' => 1,
        'yAxisID' => $axisIds[$i % count($axisIds)],
        'stepped' => true,
    ];
}

$chartData = [
    'type' => 'line',
    'data' => [
        'datasets' => $datasets,
    ],
    'options' => [
        'time' => [
            'unit' => 'day',
            'tooltipFormat' => 'MMMM d, yyyy',
        ],
        'elements' => [
            'point' => [
                'radius' => 2,
            ],
        ],
        'scales' => [
            'x' => [
                'type' => 'time',
            ],
            'y' => [
                'beginAtZero' => true,
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
            ],
        ],
    ],
];
?>

<h1>Analytics</h1>
<?php
    if (!empty($datasets)) {
?>
<canvas id="cumulativeChart"></canvas>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
    const cumulativeChart = document.getElementById('cumulativeChart');
    const viewChart = document.getElementById('viewChart');

    const chart = new Chart(cumulativeChart, <?php echo(json_encode($chartData)) ?>);
</script>
<?php
    } else {
?>
<form action="/debug/analytics" method="post">
    <div>
        <label for="username">Username:</label>
        <input type="text" id="username" name="username"> 
    </div>

    <div>
        <label for="upload">Upload ID:</label>
        <input type="text" id="upload" name="upload"> 
    </div>

    <p style="color: red;">This assumes the <code>reindex_user_info.php</code> and/or <code>recount_views.php</code> scripts are configured to run through cron.</p>
    <div>
        <input type="submit" name="submit" value="Display">
    </div>
</form>
<?php
    }
?>