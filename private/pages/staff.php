<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

namespace OpenSB\Pages;

global $twig, $database, $sb;

use OpenSB\UserData;
use OpenSB\UserRoleEnum;

$staffQueryData = $database->fetchArray(
    $database->query(
        "SELECT u.id, u.powerlevel
        FROM users u 
        WHERE u.powerlevel >= ?",
        [UserRoleEnum::Moderator->value]
    )
);

$usersData = [];
foreach ($staffQueryData as $user) {
    $userData = new UserData($database, $user["id"]);
    $usersData[] =
        [
            "id" => $user["id"],
            "info" => $userData->getUserArray(),
            "level" => $user["powerlevel"],
        ];
}

echo $twig->render('staff.twig', [
    'staff' => $usersData,
]);
