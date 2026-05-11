<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Pages;

global $auth, $twig, $database, $sb;

use Core\Utilities;
use Data\User\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getCurrentSkinName() != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}

if (!$sb->isIpLookupEnabled()) {
    die(); // temporary, FUCK
}

if (isset($_POST['ip'])) {
    $reason = $_POST['reason'] ?? "No reason specified";

    $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ip = ?", [$ip]);

    if ($ipban) {
        Utilities::notifyBanner("notify_dashboard_ip_ban_already", "/dashboard/ip_bans");
    } else {
        $database->query("INSERT INTO ip_bans (ip, reason, timestamp, author) VALUES (?, ?, ?, ?)", [
            $_POST['ip'],
            $reason,
            time(),
            $auth->getUserID()
        ]);

        Utilities::notifyBanner("notify_dashboard_ip_ban_success", "/dashboard/ip_bans", "success");
    }
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
