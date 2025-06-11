<?php
// ported from principia-web by chaziz -4/20/2023
namespace OpenSB;

global $twig, $database;

use SquareBracket\UserData;

$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = sprintf("%s,%s", (($page_number - 1) * 20), 20);

$queryData = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.about, u.title, 
       (SELECT COUNT(*) FROM uploads WHERE author = u.id) AS s_num, 
       (SELECT COUNT(*) FROM journals WHERE author = u.id) AS j_num,
       (SELECT COUNT(user) FROM user_follows WHERE id = u.id) AS f_num
        FROM users u 
        WHERE u.id NOT IN (SELECT userid FROM user_bans)
        ORDER BY u.lastview DESC LIMIT $limit"));

$countData = $database->result("SELECT COUNT(*) FROM users u WHERE u.id NOT IN (SELECT userid FROM user_bans)");

$usersData = [];
foreach ($queryData as $user)
{
    $userData = new UserData($database, $user["id"]);
    $usersData[] =
        [
            "id" => $user["id"],
            "info" => $userData->getUserArray(),
            "submissions" => $user["s_num"],
            "journals" => $user["j_num"],
            "followers" => $user["f_num"],
            "about" => $user["about"],
        ];
}

$data = [
    'users' => $usersData,
    'count' => $countData,
];

echo $twig->render('users.twig', [
	'users' => $data,
    'page' => $page_number,
]);
