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
$uploads = $database->fetchArray($database->query("WITH history AS (
    SELECT
        upload,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 1 DAY THEN views END) AS d1,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 2 DAY THEN views END) AS d2,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 3 DAY THEN views END) AS d3,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 4 DAY THEN views END) AS d4,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 5 DAY THEN views END) AS d5,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 6 DAY THEN views END) AS d6,
        MAX(CASE WHEN date = CURDATE() - INTERVAL 7 DAY THEN views END) AS d7
    FROM upload_number_history
    WHERE date BETWEEN CURDATE() - INTERVAL 7 DAY AND CURDATE() - INTERVAL 1 DAY
    GROUP BY upload
),
scored AS (
    SELECT
        u.id,
        u.title,
        u.author,
        u.type,
        u.rating,
        FROM_UNIXTIME(COALESCE(NULLIF(u.original_timestamp, 0), u.timestamp)) AS upload_date,
        u.views AS views_now,
        COALESCE(u.views - h.d7, u.views - h.d1, 0) AS views_gained_recent,

        (
            COALESCE(LEAST((u.views - h.d1) / (h.d1 + 30), 3) * 7, 0) +
            COALESCE(LEAST((h.d1 - h.d2) / (h.d2 + 30), 3) * 6, 0) +
            COALESCE(LEAST((h.d2 - h.d3) / (h.d3 + 30), 3) * 5, 0) +
            COALESCE(LEAST((h.d3 - h.d4) / (h.d4 + 30), 3) * 4, 0) +
            COALESCE(LEAST((h.d4 - h.d5) / (h.d5 + 30), 3) * 3, 0) +
            COALESCE(LEAST((h.d5 - h.d6) / (h.d6 + 30), 3) * 2, 0) +
            COALESCE(LEAST((h.d6 - h.d7) / (h.d7 + 30), 3) * 1, 0)
        )
        /
        NULLIF(
            (CASE WHEN h.d1 IS NOT NULL THEN 7 ELSE 0 END) +
            (CASE WHEN h.d1 IS NOT NULL AND h.d2 IS NOT NULL THEN 6 ELSE 0 END) +
            (CASE WHEN h.d2 IS NOT NULL AND h.d3 IS NOT NULL THEN 5 ELSE 0 END) +
            (CASE WHEN h.d3 IS NOT NULL AND h.d4 IS NOT NULL THEN 4 ELSE 0 END) +
            (CASE WHEN h.d4 IS NOT NULL AND h.d5 IS NOT NULL THEN 3 ELSE 0 END) +
            (CASE WHEN h.d5 IS NOT NULL AND h.d6 IS NOT NULL THEN 2 ELSE 0 END) +
            (CASE WHEN h.d6 IS NOT NULL AND h.d7 IS NOT NULL THEN 1 ELSE 0 END),
        0) AS raw_momentum,

        (
            (CASE WHEN h.d1 IS NOT NULL THEN 7 ELSE 0 END) +
            (CASE WHEN h.d1 IS NOT NULL AND h.d2 IS NOT NULL THEN 6 ELSE 0 END) +
            (CASE WHEN h.d2 IS NOT NULL AND h.d3 IS NOT NULL THEN 5 ELSE 0 END) +
            (CASE WHEN h.d3 IS NOT NULL AND h.d4 IS NOT NULL THEN 4 ELSE 0 END) +
            (CASE WHEN h.d4 IS NOT NULL AND h.d5 IS NOT NULL THEN 3 ELSE 0 END) +
            (CASE WHEN h.d5 IS NOT NULL AND h.d6 IS NOT NULL THEN 2 ELSE 0 END) +
            (CASE WHEN h.d6 IS NOT NULL AND h.d7 IS NOT NULL THEN 1 ELSE 0 END)
        ) / 28.0 AS data_confidence,

        u.views / (u.views + 30) AS view_confidence,

        LEAST(
            1,
            ABS(u.views - h.d1) / NULLIF(ABS(COALESCE(u.views - h.d7, u.views - h.d1, 1)), 0)
        ) AS spike_concentration
    FROM uploads u
    LEFT JOIN history h ON h.upload = u.upload_id
    WHERE u.visibility = 0
      AND NOT EXISTS (SELECT 1 FROM user_bans b WHERE b.user = u.author)
      AND u.upload_id NOT IN (SELECT upload FROM upload_takedowns)
      AND NOT EXISTS (
          SELECT 1 FROM users us
          WHERE us.id = u.author AND (us.flags & 16) = 16
      )
)
SELECT
    id, title, author, type, rating, upload_date, views_now, views_gained_recent,
    COALESCE(raw_momentum, 0) AS momentum_score,
    ROUND(spike_concentration, 2) AS spike_concentration,
    COALESCE(raw_momentum, 0)
        * data_confidence
        * view_confidence
        * POWER(GREATEST(0, 1 - spike_concentration), 2)
        * (1 + LEAST(LOG10(views_now + 1), 6))
        AS trending_score
FROM scored
ORDER BY trending_score DESC, views_now DESC
LIMIT 200;"));

foreach ($uploads as $index => $upload) {
    $database->query("INSERT INTO playlist_items (playlist, upload, position, timestamp) VALUES (?, ?, ?, ?)", 
    [$playlist_id, $upload['id'], $index, time()]);
}