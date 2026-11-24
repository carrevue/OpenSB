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

global $database, $sb;

use Actions\ActionError;
use Actions\PlaylistActions;
use Data\Playlist\PlaylistTypeEnum;
use Data\Upload\UploadFlags;
use Data\Upload\UploadVisibilityEnum;
use Data\User\UserFlags;

const POPULAR_DAYS = 7;
const POPULAR_LIMIT = 200;
const POPULAR_PER_AUTHOR = 5;

$playlists = array_column($database->fetchArray($database->query(
    "SELECT playlist_id FROM playlists WHERE type = ?",
    [PlaylistTypeEnum::PopularRightNow->value]
)), 'playlist_id');

if (!$playlists) {
    die("no popular playlist" . PHP_EOL);
}

// for the time being: this will only handle the "popular right now" playlist.
// on youtube, the "popular right now" playlist was a list of 200 most viewed
// videos posted within the last 3 days. i don't know the exact algorithm, but
// i'll do something else.
$dates = [];
for ($i = POPULAR_DAYS; $i >= 0; $i--) {
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}
$window_start = strtotime($dates[0]);

$candidates = $database->fetchArray($database->query(
    "SELECT u.id, u.upload_id, u.author, u.timestamp
     FROM uploads u
     JOIN users us ON us.id = u.author
     WHERE u.visibility = ?
       AND (u.flags & ?) = 0
       AND (us.flags & ?) = 0
       AND NOT EXISTS (SELECT 1 FROM user_bans b WHERE b.user = u.author)
       AND NOT EXISTS (SELECT 1 FROM upload_takedowns t WHERE t.upload = u.upload_id)
       AND NOT EXISTS (SELECT 1 FROM upload_deleted d WHERE d.id = u.upload_id)
       AND EXISTS (SELECT 1 FROM upload_number_history h WHERE h.upload = u.upload_id AND h.date >= ?)",
    [UploadVisibilityEnum::Public->value, UploadFlags::FLAG_UNPROCESSED->value, UserFlags::FLAG_SHADOW_BAN->value, $dates[0]]
));

$history = [];
$statement = $database->query("SELECT upload, date, views FROM upload_number_history WHERE date >= ?", [$dates[0]]);
while ($row = $statement->fetch()) {
    $history[$row["upload"]][$row["date"]] = (int)$row["views"];
}

$scores = [];
foreach ($candidates as $upload) {
    $snapshots = $history[$upload["upload_id"]] ?? [];
    $previous = null;
    $previous_day = null;
    $score = 0;

    if ((int)$upload["timestamp"] >= $window_start) {
        $previous = 0;
        $previous_day = max(0, (int)floor(((int)$upload["timestamp"] - $window_start) / 86400) - 1);
    }

    foreach ($dates as $day => $date) {
        if (!isset($snapshots[$date])) {
            continue;
        }

        if ($previous !== null && $day > $previous_day) {
            $score += max(0, $snapshots[$date] - $previous) * ($previous_day + 1 + $day) / 2;
        }

        $previous = $snapshots[$date];
        $previous_day = $day;
    }

    if ($score > 0) {
        $scores[] = ["id" => (int)$upload["id"], "author" => (int)$upload["author"], "score" => $score];
    }
}

usort($scores, fn($a, $b) => $b["score"] <=> $a["score"]);

$uploads = [];
$per_author = [];
foreach ($scores as $upload) {
    if (($per_author[$upload["author"]] ?? 0) >= POPULAR_PER_AUTHOR) {
        continue;
    }

    $per_author[$upload["author"]] = ($per_author[$upload["author"]] ?? 0) + 1;
    $uploads[] = $upload["id"];

    if (count($uploads) >= POPULAR_LIMIT) {
        break;
    }
}

$actions = new PlaylistActions($sb);

foreach ($playlists as $playlist_id) {
    $result = $actions->overwrite($playlist_id, $uploads);

    if ($result instanceof ActionError) {
        echo "$playlist_id: {$result->name}" . PHP_EOL;
    } else {
        echo "$playlist_id: " . count($uploads) . " uploads" . PHP_EOL;
    }
}