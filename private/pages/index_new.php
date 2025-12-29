<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou

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

use OpenSB\UploadFlags;
use OpenSB\UploadQuery;
use OpenSB\Utilities;
use OpenSB\UserFlags;

$upload_query = new UploadQuery($sb);

$options = $sb->getLocalOptions();

$uploads_query_limit = 15;

$uploads_random = $upload_query->query("RAND()", $uploads_query_limit);
$uploads_recent = $upload_query->query("v.timestamp DESC", $uploads_query_limit);

$uploads_featured = $upload_query->query(
    "v.timestamp DESC",
    $uploads_query_limit,
    sprintf("v.flags & %d = %d", UploadFlags::FLAG_FEATURED->value, UploadFlags::FLAG_FEATURED->value)
);

$featured_users = $database->fetchArray(
    $database->query(
        "SELECT u.id
        FROM users u 
        WHERE u.flags & ? = ?
        ORDER BY RAND() LIMIT 6",
        [UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value]
    )
);

$feed = [
    "featured" => [
        //"icon" => "/assets/profiledef_hitchhiker.svg",
        "title" => "Featured on " . $sb->getBrandingSettings()["name"],
        "uploads" => Utilities::makeUploadArray($database, $uploads_featured),
    ],
    "recent" => [
        //"icon" => "/assets/profiledef_hitchhiker.svg",
        "title" => "Recent Uploads",
        "uploads" => Utilities::makeUploadArray($database, $uploads_recent),
    ],
];

// this feels somewhat inefficient?
foreach ($featured_users as $user) {
    $feed_key = "user_" . $user["id"];
    $feed[$feed_key] = [
        //"icon" => "/assets/profiledef_hitchhiker.svg",
        "title" => Utilities::userIDToUsername($database, $user["id"]),
        "uploads" => Utilities::makeUploadArray(
            $database,
            $upload_query->query(
                "v.timestamp DESC",
                $uploads_query_limit,
                sprintf("v.author = %d", $user["id"])
            )
        ),
    ];
}

echo $twig->render('index.twig', [
    'feed' => $feed,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
