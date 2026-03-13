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

global $sb, $database, $twig;

use Data\User\UserQuery;
use Core\Utilities;
use RuntimeException;

if ($sb->getLocalOptions()['skin'] != "finalium") { throw new RuntimeException("You should be using Finalium!"); }

$user_query = new UserQuery($sb);

$queryData = $user_query->query("u.last_seen DESC", 64);
$countData = $user_query->count();
$usersData = $user_query->toArray($queryData);

echo $twig->render('debug/follow_buttons.twig', [
    'users' => $usersData,
]);