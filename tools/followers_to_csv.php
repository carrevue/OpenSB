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

namespace OpenSB;

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public');
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git');

require_once SB_PRIVATE_PATH . '/common.php';

global $database;

$path = SB_PRIVATE_PATH . '/user-followers-' . date('Y-m-d') . '.csv';

$users = $database->fetchArray($database->query("SELECT DISTINCT user FROM user_number_history ORDER BY user"));
$dates = $database->fetchArray($database->query("SELECT DISTINCT date FROM user_number_history ORDER BY date"));

$users = array_column($users, 'user');

$dates = array_filter(
    array_column($dates, 'date'),
    fn($date) => $date >= '2024-04-12'
);

$userRows = $database->fetchArray($database->query("SELECT id, title, name FROM users"));
$names   = [];
foreach ($userRows as $row) {
    $names[$row['id']]   = !empty($row['title']) ? $row['title'] : $row['name'];
}

$rows = $database->fetchArray($database->query("SELECT user, date, followers, banned FROM user_number_history"));
$data   = [];
foreach ($rows as $row) {
    $data[$row['user']][$row['date']] = ((bool)$row['banned']) ? 0 : $row['followers'];
}

$out = fopen($path, 'w');

fputcsv($out, array_merge(['user', 'name', 'avatar'], $dates), ',', '"', '\\');

foreach ($users as $user) {
    $name   = $names[$user] ?? "Deleted user $user";
    $avatar = "https://squarebracket.pw/dynamic/pfp/{$user}.png"; // TODO

    $rowData = [$user, $name, $avatar];
    foreach ($dates as $date) {
        $rowData[] = $data[$user][$date] ?? '';
    }
    fputcsv($out, $rowData, ',', '"', '\\');
}

fclose($out);

echo "The CSV has been saved at " . $path . PHP_EOL;