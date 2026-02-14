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

global $twig, $sb, $auth, $database;

use OpenSB\Utilities;

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

$limit = $database->paginate($page, 20);

$database = $sb->getDatabaseClass();
// TODO: maybe migrate this into UploadQuery?
$uploads = $database->fetchArray($database->query("SELECT v.* FROM uploads v WHERE v.upload_id NOT IN (SELECT upload FROM upload_takedowns) AND v.author = ? ORDER BY v.id DESC $limit", [$auth->getUserID()]));
$upload_count = $database->result("SELECT COUNT(*) FROM uploads u where u.author = ?", [$auth->getUserID()]);

$data = [
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "count" => $upload_count,
];

echo $twig->render('my_uploads.twig', [
    'data' => $data,
    'page' => $page,
]);
