<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $auth, $twig, $database, $orange;

use SquareBracket\UserData;
use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

$usersData = [];

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = sprintf("LIMIT %s,%s", (($page - 1) * $amount), $amount);

$usersDataQuery = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.title, u.powerlevel,
       (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
       (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num,
       (SELECT COUNT(*) FROM user_bans WHERE userid = u.id) AS is_banned
        FROM users u
        WHERE (u.name LIKE CONCAT('%', ?, '%'))
        ORDER BY u.id DESC $limit
        ",
        [$search]
    )
);

foreach ($usersDataQuery as $user) {
    $userData = new UserData($database, $user["id"]);
    $usersData[] =
        [
            "id" => $user["id"],
            "info" => $userData->getUserArray(),
            "submissions" => $user["s_num"],
            "journals" => $user["j_num"],
            "banned" => $user["is_banned"],
            "powerlevel" => $user["powerlevel"],
        ];
}

$count = $database->result(
    "SELECT COUNT(*)
        FROM users u
        WHERE (u.name LIKE CONCAT('%', ?, '%'))
        ",
    [$search]
);

echo $twig->render("admin_users.twig", [
    "users" => $usersData,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
