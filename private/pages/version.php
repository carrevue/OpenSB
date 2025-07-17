<?php

namespace OpenSB;

global $twig, $database;

use SquareBracket\VersionNumber;

$database_version = $database->getServerVersion();

// instead of using "Database software", check if we're running on MariaDB or MySQL.
// OpenSB is intended to be used with either one of these.
if (str_contains(strtolower($database_version), "maria")) {
    $database_server = "MariaDB";
} else {
    $database_server = "MySQL";
}

$data = [
    "developers" => [
        'Chaziz'
    ],
    "software" => [
        'sbVersion' => [
            'title' => "OpenSB",
            'info' => (new VersionNumber)->getVersionString(),
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
];

echo $twig->render('version.twig', [
    'data' => $data,
]);
