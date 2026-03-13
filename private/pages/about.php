<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

global $twig, $database;

use OpenSB\UserData;
use OpenSB\UserRoleEnum;
use OpenSB\VersionNumber;

// STAFF

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

// VERSION

$database_version = $database->getServerVersion();

// instead of using "Database software", check if we're running on MariaDB or MySQL.
// OpenSB is intended to be used with either one of these.
if (str_contains(strtolower($database_version), "maria")) {
    $database_server = "MariaDB";
    // remove "from Debian" portion and the likes
    $database_version = preg_replace('/\s+from.*$/i', '', $database_version);
} else {
    $database_server = "MySQL";
}

$sbVersionNumber = new VersionNumber;

$data = [
    "developers" => [
        'Chaziz'
    ],
    "software" => [
        'sbVersion' => [
            'title' => "OpenSB " . $sbVersionNumber->getVersionName(),
            'info' => $sbVersionNumber->getVersionString(),
        ],
        'phpVersion' => [
            'title' => "PHP",
            'info' => phpversion(),
        ],
        'dbVersion' => [
            'title' => $database_server,
            'info' => $database_version,
        ],
    ],
    "staff" => $usersData,
];

echo $twig->render('version.twig', [
    'data' => $data,
]);
