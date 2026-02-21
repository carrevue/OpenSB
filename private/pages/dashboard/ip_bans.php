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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Administrator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}

$amount = $_GET["amount"] ?? 24;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, $amount);

$ipData = $database->fetchArray(
    $database->query(
        "SELECT *
        FROM ip_bans
        WHERE (ip LIKE CONCAT('%', ?, '%'))
        ORDER BY timestamp DESC $limit
        ",
        [$search]
    )
);

$count = $database->result(
    "SELECT COUNT(*)
        FROM ip_bans
        WHERE (ip LIKE CONCAT('%', ?, '%'))
        ",
    [$search]
);

echo $twig->render("dashboard_ip_bans.twig", [
    "ips" => $ipData,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
