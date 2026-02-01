<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\UploadQuery;
use OpenSB\UploadFlags;

include_once('_include.php');

// page-specific shit Here.

$upload_query = new UploadQuery($sb);

$tabs = [
    "recent" => [
        "name" => "new",
        "order" => "timestamp DESC",
        "where" => "v.author = ?",
    ],
    "popular" => [
        "name" => "popular",
        "order" => "views DESC",
        "where" => "v.author = ?",
    ],
    "featured" => [
        "name" => "featured",
        "order" => "v.timestamp DESC",
        "where" => sprintf("v.author = ? AND (v.flags & %d) = 1", UploadFlags::FLAG_FEATURED->value),
    ],
    "random" => [
        "name" => "random",
        "order" => "RAND()",
        "where" => "v.author = ?",
    ],
];

$type = ($_GET['type'] ?? 'recent');
$user = ($_GET['user'] ?? null);
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit = $database->paginate($page, 20);

$uploads = $upload_query->query($tabs[$type]["order"] ?? "timestamp DESC", $limit, $tabs[$type]["where"] ?? null, [$data["id"]]);
$upload_count = $upload_query->count("v.author = ?", [$data["id"]]);

$page_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["userlink_color"],
    "about" => ($data["about"] ?? null),
    "customization" => $profile_customization_data?->getData() ?? false,
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "count" => $upload_count,
];

if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapSkinProfileGradient($data["userlink_color"]);
}

echo $twig->render('profile_browse.twig', [
    'data' => $page_data,
    'page' => $page,
    'type' => $type,
    'tabs' => $tabs,
]);
