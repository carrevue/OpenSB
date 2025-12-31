<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
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

global $twig, $sb, $database;

use OpenSB\Utilities;
use OpenSB\UploadQuery;

$upload_query = new UploadQuery($sb);

function getOrderFromType($type): string
{
    $order = match ($type) {
        'recent' => "v.timestamp DESC",
        'popular' => "views DESC",
        'random' => "RAND()",
        default => "v.timestamp DESC",
    };
    return $order;
}

$type = ($_GET['type'] ?? 'recent');
$user = ($_GET['user'] ?? null);
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

$order = getOrderFromType($type);
$limit = $database->paginate($page, 20);

if ($user) {
    Utilities::redirect("/user/$user/uploads" . ($type !== 'recent' ? "?type=$type" : ''), 301);
} else {
    $uploads = $upload_query->query($order, $limit);
    $upload_count = $upload_query->count();
}

$data = [
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "count" => $upload_count,
];

echo $twig->render('browse.twig', [
    'user' => $user,
    'data' => $data,
    'page' => $page,
    'type' => $type,
]);
