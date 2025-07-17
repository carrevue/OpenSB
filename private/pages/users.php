<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz
  Copyright (C) 2022-2023 ROllerozxa

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
        ORDER BY u.lastview DESC LIMIT $limit"
    )
);

$countData = $database->result("SELECT COUNT(*) FROM users u WHERE u.id NOT IN (SELECT userid FROM user_bans)");

$usersData = [];
foreach ($queryData as $user) {
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
