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

global $database;

$uploads = $database->fetchArray($database->query("SELECT * FROM uploads ORDER BY views DESC"));

// hardcoded shit from the sb db! wow!
const POKTUBE_TIMESTAMP = 1619236800; // poktube from 2021 views were not properly counted and are fucked
const SB_2022_TIMESTAMP = 1662664200; // sb views from 2021-2022 were not counted properly
const QOBO_TIMESTAMP = 1709269200; // qobo views from 2023 were FUCKED and had a lot of botting.
const SB_2024_TIMESTAMP = 1730782800; // 2024 squarebracket (pre-nov5)

$database->beginTransaction();

foreach ($uploads as $upload) {
    $views = $database->fetchArray($database->query("
        SELECT type, timestamp
        FROM upload_views 
        WHERE upload_id = ?
    ", [$upload["upload_id"]]));

    $total = $database->result("SELECT COUNT(*) FROM upload_views WHERE upload_id = ?", [$upload["upload_id"]]);

    $loggedIn = 0;
    $loggedOut = 0;
    $adjustedViews = 0;

    foreach ($views as $view) {
        $isLoggedIn = ($view['type'] === 'user');
        $timestamp = (int)$view['timestamp'];

        if ($isLoggedIn) {
            // logged-in views should always be 1:1
            $loggedIn++;
            $adjustedViews += 1;
        } else {
            $loggedOut++;

            // these timestamps are hardcoded within the db.
            // sb did not count the exact timestamp of views until about april 2024.
            if ($timestamp === POKTUBE_TIMESTAMP) {
                $adjustedViews += 0.05;
            } elseif ($timestamp === QOBO_TIMESTAMP) {
                $adjustedViews += 0.025;
            } elseif ($timestamp === SB_2022_TIMESTAMP) {
                $adjustedViews += 0.075;
            } else {
                $ratio_penalty = 5;

                // crawlerdetect was kinda fucky during this time
                if ($timestamp > QOBO_TIMESTAMP || $timestamp < SB_2024_TIMESTAMP) {
                    $ratio_penalty = 10;
                }

                if ($loggedIn > 0) {
                    // ratio. PHP_INT_MAX is there to avoid division by zero errors.
                    $ratio = $loggedIn ? (1 + ($ratio_penalty / ($loggedIn + 1))) : PHP_INT_MAX;
                    $adjustedViews += (1 / $ratio);
                } else {
                    $adjustedViews += 0.05;
                }
            }
        }
    }

    if (SB_CLI && getenv('TERM')) {
        // debug output shit
        echo "ID: {$upload["upload_id"]} ";
        echo "Views (U/G/O/N): {$loggedIn}/{$loggedOut}/{$upload["views"]}/" . round($adjustedViews) . PHP_EOL;
    }

    // now push that shit to the database
    $database->query(
        "UPDATE uploads SET views = ? WHERE upload_id = ?",
        [round($adjustedViews), $upload["upload_id"]]
    );

    if (!$database->result("SELECT upload FROM upload_number_history WHERE upload = ? AND date = ?", [$upload["upload_id"], date('Y-m-d')])) {
        $database->query(
        "INSERT INTO upload_number_history (upload, date, views, views_raw)
        VALUES (?,?,?,?)",
            [$upload["upload_id"], date('Y-m-d'), round($adjustedViews), $total]
        );
    }
}

$database->commitTransaction();

if ($sb->isDiscordWebhookEnabled()) {
    $sb->getDiscordWebhookClass()->scriptSuccessHook(__FILE__);
}