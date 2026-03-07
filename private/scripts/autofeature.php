#!/usr/bin/php
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

$found = false;

if (SB_CLI && getenv('TERM')) {
    // debug output shit
    echo "Autofeature" . PHP_EOL;
}

// get a list of uploads from after the newest featured one
$uploads = $database->fetchArray($database->query(
    "SELECT * FROM uploads 
     WHERE timestamp > (
         SELECT timestamp FROM uploads 
         WHERE (flags & 1) != 0 -- 1 is UploadFlags::Featured 
         ORDER BY timestamp DESC 
         LIMIT 1
     )
     ORDER BY views DESC" // use the indexed count rather than the raw one, since that's the more "accurate" number
));

$featured = $database->fetchArray($database->query(
    "SELECT views FROM uploads 
     WHERE (flags & 1) != 0 -- 1 is UploadFlags::Featured 
     ORDER BY timestamp DESC 
     LIMIT 12"
));

$featured_views = array_column($featured, 'views');
sort($featured_views);
$median = $featured_views[intval(count($featured_views) / 2)];
$threshold = round($median / 1.5);

echo "Threshold: " . $threshold . PHP_EOL;

foreach ($uploads as $upload) {
    // check if the author is
    // * not banned
    // * more than a day old
    // * not staff
    $author_data = new UserData($database, $upload["author"]);
    
    if ($author_data->isUserBanned() || 
        $author_data->getUserArray()["joined"] > (time() - 86400) ||
        $author_data->getUserArray()["powerlevel"] != 1
    ) {
        continue;
    }

    // check if the upload
    // * has views above to the threshold
    // * todo ???
    if ($upload["views"] >= $threshold) {
        $found = true;
        echo "FOUND: " . $upload["title"] . PHP_EOL;

        $flags = $upload["flags"];
        $flags |= UploadFlags::FLAG_FEATURED->value;

        $database->query(
            "UPDATE uploads SET flags = ? WHERE id = ?",
            [$flags, $upload["id"]]
        );
        break;
    }
}

if (!$found) {
    echo("Couldn't find suitable upload.") . PHP_EOL;
}

if ($sb->isDiscordWebhookEnabled()) {
    $sb->getDiscordWebhookClass()->scriptSuccessHook(__FILE__);
}