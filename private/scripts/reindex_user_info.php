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

$users = $database->fetchArray($database->query("SELECT * FROM users"));

$database->beginTransaction();

foreach ($users as $user) {
    $numbers = $database->fetch("
        SELECT
            (SELECT COUNT(*) FROM uploads WHERE author = u.id AND upload_id NOT IN (SELECT upload FROM upload_takedowns)) AS u_num,
            (SELECT COUNT(user) FROM user_follows WHERE id = u.id AND user NOT IN (SELECT user FROM user_bans)) AS f_num
        FROM users u WHERE u.id = ?",
        [$user["id"]]
    );

    $is_banned = (int)(bool)$database->result("SELECT id FROM user_bans WHERE user = ?", [$user["id"]]);

    $database->query(
        "UPDATE users SET u_index = ?, f_index = ? WHERE id = ?",
        [$numbers["u_num"], $numbers["f_num"], $user["id"]]
    );

    $last = $database->fetch(
        "SELECT followers, uploads FROM user_number_history WHERE user = ? ORDER BY date DESC LIMIT 1",
        [$user["id"]]
    );

    $today_exists = $database->result(
        "SELECT user FROM user_number_history WHERE user = ? AND date = ?",
        [$user["id"], date('Y-m-d')]
    );

    if (!$today_exists && (!$last || $last["followers"] != $numbers["f_num"] || $last["uploads"] != $numbers["u_num"])) {
        $database->query(
            "INSERT INTO user_number_history (user, date, followers, uploads, banned) VALUES (?,?,?,?,?)",
            [$user["id"], date('Y-m-d'), $numbers["f_num"], $numbers["u_num"], $is_banned]
        );
    }
}

$database->commitTransaction();

if ($sb->isDiscordWebhookEnabled()) {
    $sb->getDiscordWebhookClass()->scriptSuccessHook(__FILE__);
}