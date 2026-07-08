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

namespace Pages;

global $auth, $database, $twig, $sb;

use Core\Utilities;
use Data\Upload\UploadQuery;
use Data\Upload\UploadFlags;
use Data\Upload\UploadTypeEnum;

include_once('_include.php');

// page-specific shit Here.

$page_path = Utilities::getPathAsArray()[2] ?? null;

$upload_query = new UploadQuery($sb);

$upload_type_filter = match ($page_path) {
    "videos" => " AND v.type = " . UploadTypeEnum::Video->value,
    "images" => " AND v.type = " . UploadTypeEnum::Image->value,
    default => "",
};

$base_where = "v.author = ?" . $upload_type_filter;

$tabs = [
    "recent" => [
        "name" => "new",
        "order" => "uploaded DESC",
        "where" => $base_where,
    ],
    "popular" => [
        "name" => "popular",
        "order" => "views DESC",
        "where" => $base_where,
    ],
    "featured" => [
        "name" => "featured",
        "order" => "uploaded DESC",
        "where" => sprintf("v.author = ? AND (v.flags & %d) != 0", UploadFlags::FLAG_FEATURED->value) . $upload_type_filter,
    ],
    "random" => [
        "name" => "random",
        "order" => "RAND()",
        "where" => $base_where,
    ],
];

$type = ($_GET['type'] ?? 'recent');
$user = ($_GET['user'] ?? null);
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$limit_num = ($sb->getCurrentSkinName() == "finalium") ? 30 : 20;

$limit = $database->paginate($page, $limit_num);

$uploads = $upload_query->query($tabs[$type]["order"] ?? "timestamp DESC", $limit, $tabs[$type]["where"] ?? null, [$data["id"]]);
$upload_count = $upload_query->count("v.author = ?" . $upload_type_filter, [$data["id"]]);

$page_data = [
    "uploads" => $uploads->toCleanArray(),
    "count" => $upload_count,
];

if ($sb->getCurrentSkinName() == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapSkinProfileGradient($data["userlink_color"]);
}

echo $twig->render('profile_browse.twig', [
    'common' => $common_data,
    'data' => $page_data,
    'page' => $page,
    'type' => $type,
    'tabs' => $tabs,
    'items_per_page' => $limit_num,
    'page_path' => $page_path,
]);
