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

$path = SB_PRIVATE_PATH . '/views-' . date('Y-m-d') . '.csv';

$uploads = $database->fetchArray($database->query("SELECT DISTINCT upload FROM upload_number_history ORDER BY upload"));
$dates   = $database->fetchArray($database->query("SELECT DISTINCT date FROM upload_number_history ORDER BY date"));

$uploads = array_column($uploads, 'upload');

$dates = array_filter(
    array_column($dates, 'date'),
    fn($date) => $date >= '2024-04-12'
);

$uploadRows = $database->fetchArray($database->query("SELECT upload_id, title FROM uploads"));
$titles = [];
foreach ($uploadRows as $row) {
    $titles[$row['upload_id']] = $row['title'];
}

$rows = $database->fetchArray($database->query("SELECT upload, date, views FROM upload_number_history"));
$data = [];
foreach ($rows as $row) {
    $data[$row['upload']][$row['date']] = $row['views'];
}

$out = fopen($path, 'w');

fputcsv($out, array_merge(['upload', 'name', 'image'], $dates));

foreach ($uploads as $upload) {
    $name  = $titles[$upload] ?? "Deleted upload $upload";
    $image = "https://squarebracket.pw/dynamic/thumbnails/{$upload}.png"; // TODO

    $rowData = [$upload, $name, $image];
    foreach ($dates as $date) {
        $rowData[] = $data[$upload][$date] ?? '';
    }
    fputcsv($out, $rowData);
}

fclose($out);

echo "The CSV has been saved at " . $path . PHP_EOL;