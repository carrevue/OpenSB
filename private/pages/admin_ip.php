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

global $auth, $twig, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdministrator()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, $amount);

$ipData = $database->fetchArray(
    $database->query(
        "SELECT *
        FROM ip_bans
        WHERE (ip LIKE CONCAT('%', ?, '%'))
        ORDER BY time DESC $limit
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

echo $twig->render("admin_ip.twig", [
    "ips" => $ipData,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
