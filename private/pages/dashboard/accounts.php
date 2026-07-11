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

$amount = $_GET["amount"] ?? 24;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, $amount);

$accounts = $database->fetchArray(
    $database->query(
        "SELECT *
        FROM accounts
        WHERE (email LIKE CONCAT('%', ?, '%'))
        ORDER BY registered DESC $limit
        ",
        [$search]
    )
);

$count = $database->result(
    "SELECT COUNT(*)
        FROM accounts
        WHERE (email LIKE CONCAT('%', ?, '%'))
        ",
    [$search]
);

// gravatar hash
foreach ($accounts as &$account) {
    $account["gravatar"] = hash('sha256', strtolower(trim($account["email"])));
}

echo $twig->render("dashboard_accounts.twig", [
    "accounts" => $accounts,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
