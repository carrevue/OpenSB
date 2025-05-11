<?php

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

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::notifyBanner("Please change your skin to Biscuit.", "/theme");
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
        ", [$search]));

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
        ", [$search]);

echo $twig->render("admin_users.twig", [
    "users" => $usersData,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);