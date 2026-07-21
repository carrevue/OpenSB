<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

namespace Pages;

global $twig, $sb, $database;

use Core\Utilities;
use Data\Upload\UploadQuery;
use Data\Upload\UploadFlags;

$upload_query = new UploadQuery($sb);

$tabs = [
    "recent" => [
        "name" => "new",
        "order" => "uploaded DESC",
        "where" => null,
    ],
    "featured" => [
        "name" => "featured",
        "order" => "uploaded DESC",
        "where" => sprintf("(v.flags & %d) = 1", UploadFlags::FLAG_FEATURED->value),
    ],
    "popular" => [
        "name" => "popular",
        "order" => "views DESC",
        "where" => null,
    ],
];

$type = ($_GET['type'] ?? 'recent');
$user = ($_GET['user'] ?? null);
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit = $database->paginate($page, 20);

if ($user) {
    Utilities::redirect("/user/$user/uploads" . ($type !== 'recent' ? "?type=$type" : ''), 301);
} else {
    $uploads = $upload_query->query($tabs[$type]["order"] ?? "timestamp DESC", $limit, $tabs[$type]["where"] ?? null)->toCleanArray();
    $upload_count = $upload_query->count($tabs[$type]["where"] ?? null);
}

$data = [
    "uploads" => $uploads,
    "count" => $upload_count,
];

echo $twig->render('browse.twig', [
    'data' => $data,
    'page' => $page,
    'type' => $type,
    'tabs' => $tabs,
]);
