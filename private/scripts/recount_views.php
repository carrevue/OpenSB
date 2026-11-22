<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

define("SB_ROOT_PATH", dirname(__DIR__, 2));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once SB_PRIVATE_PATH . '/common.php';

use Core\IPBans;

global $database, $sb;

// hardcoded shit from the sb db! wow!
// these timestamps are hardcoded within the db.
const POKTUBE_TIMESTAMP = 1619236800; // poktube from 2021 views were not properly counted and are fucked
const SB_2022_TIMESTAMP = 1662664200; // sb views from 2021-2022 were not counted properly
const QOBO_TIMESTAMP = 1709269200; // qobo views from 2023 were FUCKED and had a lot of botting.
const SB_2024_TIMESTAMP = 1735707600; // 2024 squarebracket views
const SB_2025_TIMESTAMP = 1753070400; // views before july 21st 2025 don't have ips

const LEGACY_WEIGHTS = [
    POKTUBE_TIMESTAMP => 0.0125,
    SB_2022_TIMESTAMP => 0.01875,
    QOBO_TIMESTAMP => 0.00625,
    SB_2025_TIMESTAMP => 0.25, // guest views before this can't be grouped by ip range
];

// views from the same /24 (or /64 for ipv6) on the same upload count once
function ip_range(string $ip): string
{
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return $ip;
    }
    return strlen($packed) === 4 ? substr($packed, 0, 3) : substr($packed, 0, 8);
}

function guest_weight(int $loggedIn, int $timestamp): float
{
    $weight = $timestamp < SB_2025_TIMESTAMP ? LEGACY_WEIGHTS[SB_2025_TIMESTAMP] : 1;
    $ratio_penalty = 5;

    // crawlerdetect was kinda fucky during this time
    if ($timestamp > QOBO_TIMESTAMP && $timestamp < SB_2024_TIMESTAMP) {
        $ratio_penalty = 10;
    }

    if ($loggedIn > 0) {
        // ratio. PHP_INT_MAX is there to avoid division by zero errors.
        $ratio = $loggedIn ? (1 + ($ratio_penalty / ($loggedIn + 1))) : PHP_INT_MAX;
        return $weight / $ratio;
    }

    return $weight * 0.05;
}

$bans = new IPBans($database, $sb->isIpLookupEnabled() ? $sb->getIpLookupClass() : null);

$uploads = [];
$byLowercase = [];
foreach ($database->fetchArray($database->query("SELECT id, upload_id, views FROM uploads")) as $row) {
    // a few upload_ids are on more than one row, they all get the same count
    if (isset($uploads[$row['upload_id']])) {
        $uploads[$row['upload_id']]['rows'][(int)$row['id']] = (int)$row['views'];
        continue;
    }
    $uploads[$row['upload_id']] = ['rows' => [(int)$row['id'] => (int)$row['views']], 'total' => 0, 'user' => 0, 'users' => [], 'legacy' => 0.0, 'guests' => []];
    $byLowercase[strtolower($row['upload_id'])][] = $row['upload_id'];
}

$banned = 0;
// oldest first, so guest views are weighed against the logged-in views the upload had at the time
$statement = $database->query("SELECT upload_id, user, timestamp, type FROM upload_views ORDER BY timestamp");
while ($view = $statement->fetch()) {
    $uploadId = $view['upload_id'];

    // some views on prod were logged with a lowercased upload id???
    if (!isset($uploads[$uploadId])) {
        $matches = $byLowercase[strtolower($uploadId)] ?? [];
        if (count($matches) !== 1) {
            continue;
        }
        $uploadId = $matches[0];
    }

    $count = &$uploads[$uploadId];
    $count['total']++;
    $timestamp = (int)$view['timestamp'];

    if ($timestamp >= SB_2025_TIMESTAMP && $bans->isBanned($view['user'])) {
        $banned++;
    } elseif ($view['type'] === 'user') {
        // logged-in views are 1:1, except ones from the same ip range
        $count['user']++;
        if ($timestamp >= SB_2025_TIMESTAMP) {
            $count['users']['r' . ip_range($view['user'])] = true;
        } else {
            $count['users'][] = true;
        }
    } elseif ($timestamp < SB_2025_TIMESTAMP && isset(LEGACY_WEIGHTS[$timestamp])) {
        // sb did not count the exact timestamp of views until april 2024.
        $count['legacy'] += LEGACY_WEIGHTS[$timestamp];
    } elseif ($timestamp >= SB_2025_TIMESTAMP) {
        $count['guests']['r' . ip_range($view['user'])] ??= guest_weight(count($count['users']), $timestamp);
    } else {
        $count['guests'][] = guest_weight(count($count['users']), $timestamp);
    }
    unset($count);
}

$today = date('Y-m-d');
$changed = [];
$history = [];
foreach ($uploads as $uploadId => $count) {
    $views = (int)round(count($count['users']) + $count['legacy'] + array_sum($count['guests']));

    if (SB_CLI && getenv('TERM')) {
        // debug output shit
        echo "ID: {$uploadId} ";
        echo "Views (U/G/O/N): {$count['user']}/" . ($count['total'] - $count['user']) . "/" . reset($count['rows']) . "/{$views}" . PHP_EOL;
    }

    foreach ($count['rows'] as $id => $old) {
        if ($views !== $old) {
            $changed[] = [$id, $views];
        }
    }
    $history[] = [$uploadId, $today, $views, $count['total']];
}

// now push that shit to the database
$database->beginTransaction();

$database->query("CREATE TEMPORARY TABLE recount_views (id int(11) NOT NULL PRIMARY KEY, views int(11) NOT NULL)");
foreach (array_chunk($changed, 1000) as $chunk) {
    $database->query(
        "INSERT INTO recount_views (id, views) VALUES " . implode(',', array_fill(0, count($chunk), '(?,?)')),
        array_merge(...$chunk)
    );
}
$database->query("UPDATE uploads v JOIN recount_views r ON r.id = v.id SET v.views = r.views");
$database->query("DROP TEMPORARY TABLE recount_views");

foreach (array_chunk($history, 1000) as $chunk) {
    $database->query(
        "INSERT IGNORE INTO upload_number_history (upload, date, views, views_raw) VALUES " . implode(',', array_fill(0, count($chunk), '(?,?,?,?)')),
        array_merge(...$chunk)
    );
}

$database->commitTransaction();

if (SB_CLI) {
    echo count($uploads) . " uploads, " . count($changed) . " changed, $banned views from banned ips/asns ignored" . PHP_EOL;
}

if ($sb->isWebhookLoggerEnabled()) {
    $sb->getWebhookLoggerClass()->scriptSuccessHook(__FILE__);
}