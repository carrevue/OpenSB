<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz
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

namespace Pages;

global $twig, $database, $sb;

use Data\User\UserQuery;
use Core\Utilities;

$user_query = new UserQuery($sb);

$tabs = [
    "recent" => [
        "name" => "last_active",
        "order" => "u.last_seen DESC",
        "where" => null,
    ],
    "new" => [
        "name" => "new",
        "order" => "u.joined DESC",
        "where" => null,
    ],
    "popular" => [
        "name" => "popular",
        "order" => "u.f_index DESC",
        "where" => null,
    ],
];

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$usersData = $user_query->query($tabs[$type]["order"] ?? "u.last_seen DESC", $limit, "u_index != 0")->toCleanArray();
$countData = $user_query->count("u_index != 0");

$data = [
    'users' => $usersData,
    'count' => $countData,
];

echo $twig->render('members.twig', [
    'users' => $data,
    'page' => $page,
    'type' => $type,
    'tabs' => $tabs,
]);
