<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

namespace OpenSB\Pages\Debug;

global $sb, $twig, $database;

use OpenSB\VersionNumber;
use BluffingoCore\CoreVersionNumber;

$database_version = $database->getServerVersion();

// instead of using "Database software", check if we're running on MariaDB or MySQL.
// OpenSB is intended to be used with either one of these.
if (str_contains(strtolower($database_version), "maria")) {
    $database_server = "MariaDB";
} else {
    $database_server = "MySQL";
}

$sbVersionNumber = new VersionNumber;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$localization = $sb->getLocalizationClass();

$page = [
    "title" => "Version",
    "sections" => [
        [
            "title" => "Version",
            "contents" => [
                "texts" => [
                    $localization->translate("version_intro", "2021-" . date("Y"), "Chaziz"),
                    $localization->translate("version_agpl"),
                    $localization->translate("version_no_warranty"),
                    $localization->translate("version_license_notice", "/license", "http://www.gnu.org/licenses/"),
                    $localization->translate("version_github", "https://github.com/bluffingo/OpenSB"),
                ],
            ],
        ],
        [
            "contents" => [
                "list" => [
                    "OpenSB " . $sbVersionNumber->getVersionName() => $sbVersionNumber->getVersionString(),
                    "BluffingoCore" => (new CoreVersionNumber)->getVersionString(),
                    "PHP" => phpversion(),
                    $database_server => $database_version,
                ]
            ],
        ],
    ],
];

echo $twig->render('_agnostic.twig', ['page' => $page]);
