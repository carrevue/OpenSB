<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

global $sb, $twig;

use OpenSB\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$page = [
    "tabs" => [
        "overview" => [
            "name" => "Overview",
        ],
        "users" => [
            "name" => "Users",
        ],
        "ipbans" => [
            "name" => "IP bans",
        ],
        "uploads" => [
            "name" => "Uploads",
        ],
    ],
    "title" => "Overview",
    "sections" => [
        [
            "title" => "Over the years",
            "contents" => [
                "buttons" => [
                    [
                        "color" => "primary",
                        "text" => "Primary Button",
                    ],
                    [
                        "color" => "secondary",
                        "text" => "Secondary Button",
                    ],
                    [
                        "color" => "warning",
                        "text" => "Warning Button",
                    ],
                    [
                        "color" => "danger",
                        "text" => "Danger Button",
                    ],
                    [
                        "color" => "success",
                        "text" => "Success Button",
                    ],
                ],
            ],
        ],
        [
            "title" => "Server details",
            "contents" => [
                "list" => [
                    "Uname" => php_uname(),
                    "List entry" => "Content",
                ]
            ],
        ],
        [
            "title" => "Statistics",
            "contents" => [
                "list" => [
                    "List entry" => "Content",
                ]
            ],
        ],
    ],
];

echo $twig->render('_agnostic.twig', ['page' => $page]);
