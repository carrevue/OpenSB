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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\UserData;
use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

$usersData = [];

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, $amount);

$usersDataQuery = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.title, u.powerlevel,
       (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
       (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num
       /*(SELECT COUNT(*) FROM user_bans WHERE user = u.id) AS is_banned*/
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
            "uploads" => $user["s_num"],
            "journals" => $user["j_num"],
            //"banned" => $user["is_banned"],
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

echo $twig->render("dashboard_users.twig", [
    "users" => $usersData,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
