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

// ported from principia-web by chaziz -4/20/2023

namespace OpenSB\Pages;

global $twig, $database, $sb;

use OpenSB\UserQuery;
use OpenSB\Utilities;

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$user_query = new UserQuery($sb);

$queryData = $user_query->query("u.last_seen DESC", $limit);
$countData = $user_query->count();
$usersData = Utilities::makeUserArray($database, $queryData);

$data = [
    'users' => $usersData,
    'count' => $countData,
];

echo $twig->render('users.twig', [
    'users' => $data,
    'page' => $page,
]);
