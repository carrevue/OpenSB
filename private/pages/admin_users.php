<?php

namespace OpenSB;

global $auth, $twig, $database, $orange;

use SquareBracket\Utilities;
use SquareBracket\UserData;

if (!$auth->isUserAdmin()) {
    Utilities::bannerNotification("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::bannerNotification("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::bannerNotification("Please change your skin to Biscuit.", "/theme");
}

$usersData = [];

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? "";
$page = $_GET["page"] ?? 1;

$limit = sprintf("LIMIT %s,%s", (($page - 1) * $amount), $amount);

$usersDataQuery = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.title, u.ip, u.powerlevel,
       (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
       (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num,
       (SELECT COUNT(*) FROM user_bans WHERE userid = u.id) AS is_banned
        FROM users u
        WHERE (u.name LIKE CONCAT('%', ?, '%'))
        ORDER BY u.id DESC $limit
        ", [$search]));

$countedIps = array_count_values(array_column($usersDataQuery, 'ip'));

foreach ($usersDataQuery as $user) {
    $class = null;

    // NOTE: 999.999.999.999 is the default value of IPs in the DB.
    // accounts may still have "999.999.999.999" if they haven't been logged into
    // before like, late-2023? i don't know, it's kinda fucky. -chaziz 6/29/2024

    // 99Hz2rHAYm9Vo is the result of crypt('999.999.999.999', '999.999.999.999') -chaziz 1/3/2025
    if ($countedIps[$user["ip"]] > 1 && ($user["ip"] != "99Hz2rHAYm9Vo" && $user["ip"] != "localhost")) {
        $class = "unbanned-other-unbanned";
    }

    if ($user["powerlevel"] > 1) {
        $class = "staff";
    }

    if ($user["is_banned"]) {
        $class = "banned";
    }

    $userData = new UserData($database, $user["id"]);
    $usersData[] =
        [
            "id" => $user["id"],
            "info" => $userData->getUserArray(),
            "submissions" => $user["s_num"],
            "journals" => $user["j_num"],
            "banned" => $user["is_banned"],
            "class" => $class,
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