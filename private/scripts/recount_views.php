<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

define("BLUFF_ROOT_PATH", dirname(__DIR__, 2));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once BLUFF_PRIVATE_PATH . '/common.php';

global $database;

$uploads = $database->fetchArray($database->query("SELECT * FROM uploads ORDER BY views DESC"));

// hardcoded shit from the sb db! wow!
const POKTUBE_TIMESTAMP = 1619236800; // poktube from 2021 views were not properly counted and are fucked
const BLUFF_2022_TIMESTAMP = 1662664200; // sb views from 2021-2022 were not counted properly
const QTV_TIMESTAMP = 1709269200; // qtv views from 2023 were FUCKED and had a lot of botting.
const BLUFF_2024_TIMESTAMP = 1730782800; // qtv views from 2023 were FUCKED and had a lot of botting.

// some of these view counts are a little too fishy
// i cant actually get the code to fix these so just hardcode some of the shit
const PENALIZED_UPLOADS = [
    "kLTc06kfmmD" => 0.15, // Millions of players are doing the stupidest sh- on Roblox
    "IHqkcCdlTNq" => 0.15, // Sparta Remix Tutorial #1: Pitch Patterns!
    "HKMmeNcyiUI" => 0.1, // youtube whenever gamerappa uploads a video
    "yoc7poNGzzp" => 0.08, // this site works on the wii
    "i0ygjnoOSIX" => 0.05, // Charla Serbia
    "SoESPPtBKym" => 0.05, // bluey dialer
    "hfBv-jEq39y" => 0.035, // BANDIT AND PAT ARE GAY (PROOF)T
    "VoFUj4A7lbW" => 0.025, // Genuine™ Chip Chilla™ Plushies™
    "h5KVbDgrctS" => 0.025, // Woke™ Chip Chilla™ Plushies™
    "07z4X_T-ZqA" => 0.015, // Chip Chilla Redraw Attempt
    "qSvVjP2nx4w" => 0.01, // Alfie (Bluey) x Rocky (Several Robloxians)
    "d3F-g9LtnZI" => 0.01, // Chazgame2's Transformation
];

foreach ($uploads as $upload) {
    $views = $database->fetchArray($database->query("
        SELECT type, timestamp 
        FROM upload_views 
        WHERE video_id = ?
    ", [$upload["video_id"]]));

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
                $adjustedViews += 0.0333333;
            } elseif ($timestamp === QTV_TIMESTAMP) {
                $adjustedViews += 0.05;
            } elseif ($timestamp === BLUFF_2022_TIMESTAMP) {
                $adjustedViews += 0.25;
            } else {
                // penalize certain uploads
                if (array_key_exists($upload["video_id"], PENALIZED_UPLOADS)) {
                    $adjustedViews += PENALIZED_UPLOADS[$upload["video_id"]];
                } else {
                    $ratio_penalty = 10;

                    // crawlerdetect was kinda fucky during this time
                    if ($timestamp > QTV_TIMESTAMP || $timestamp < BLUFF_2024_TIMESTAMP) {
                        $ratio_penalty = 25;
                    }

                    // these videos were directly linked onto youtube, so most of the guest views are genuine.
                    if ($upload["video_id"] === "rpdCM7mawrL" || $upload["video_id"] === "I6Dhqvit5rd") {
                        $ratio_penalty = 7.5;
                    }

                    // ratio. PHP_INT_MAX is there to avoid division by zero errors.
                    $ratio = $loggedIn ? (1 + ($ratio_penalty / ($loggedIn + 1))) : PHP_INT_MAX;

                    $adjustedViews += (1 / $ratio);
                }
            }
        }
    }

    // debug output shit
    echo "ID: {$upload["video_id"]} ";
    echo "Views (U/G/O/N): {$loggedIn}/{$loggedOut}/{$upload["views"]}/" . round($adjustedViews) . PHP_EOL;

    // now push that shit to the database
    $database->query(
        "UPDATE uploads SET views = ? WHERE video_id = ?",
        [round($adjustedViews), $upload["video_id"]]
    );
}
