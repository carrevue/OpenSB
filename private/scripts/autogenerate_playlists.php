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

define("SB_ROOT_PATH", dirname(__DIR__, 2));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once SB_PRIVATE_PATH . '/common.php';

global $database;

// Oops! Temporarily Hardcoded!
$playlist_id = 1;

$database->query("DELETE FROM playlist_items WHERE playlist = ?", [$playlist_id]);

// for the time being: this will only handle the "popular right now" playlist.
// on youtube, the "popular right now" playlist was a list of 200 most viewed
// videos posted within the last 3 days. i don't know the exact algorithm, but
// i'll do something else.
$uploads = $database->fetchArray($database->query("SELECT
    u.id,
    u.title,
    u.author,
    u.type,
    u.rating,
    FROM_UNIXTIME(u.timestamp) AS upload_date,
    u.views AS views_now,
    (u.views - h.d3) AS views_gained_3d,
    (
        (COALESCE(u.views, h.d1, 0) - COALESCE(h.d1, u.views, 0)) * 3 +
        (COALESCE(h.d1, h.d2, 0) - COALESCE(h.d2, h.d1, 0)) * 2 +
        (COALESCE(h.d2, h.d3, 0) - COALESCE(h.d3, h.d2, 0)) * 1
    ) AS activity_score,
    GREATEST(0, 30 - DATEDIFF(CURDATE(), FROM_UNIXTIME(u.timestamp))) / 30 AS age_boost,
    (
        (
            (COALESCE(u.views, h.d1, 0) - COALESCE(h.d1, u.views, 0)) * 3 +
            (COALESCE(h.d1, h.d2, 0) - COALESCE(h.d2, h.d1, 0)) * 2 +
            (COALESCE(h.d2, h.d3, 0) - COALESCE(h.d3, h.d2, 0)) * 1
        ) * (1 + GREATEST(0, 30 - DATEDIFF(CURDATE(), FROM_UNIXTIME(u.timestamp))) / 30)
        + LOG10(u.views + 1)
    ) AS trending_score
FROM (
    SELECT
        upload,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 1 DAY THEN views END) AS d1,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 2 DAY THEN views END) AS d2,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 3 DAY THEN views END) AS d3
    FROM upload_number_history
    WHERE date BETWEEN CURDATE() - INTERVAL 3 DAY AND CURDATE() - INTERVAL 1 DAY
    GROUP BY upload
) h
JOIN uploads u ON u.upload_id = h.upload
WHERE author NOT IN (SELECT user FROM user_bans)
ORDER BY trending_score DESC
LIMIT 200;"));

foreach ($uploads as $index => $upload) {
    $database->query("INSERT INTO playlist_items (playlist, upload, position, timestamp) VALUES (?, ?, ?, ?)", 
    [$playlist_id, $upload['id'], $index, time()]);
}