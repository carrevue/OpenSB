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

namespace OpenSB;

global $twig, $orange, $auth, $database;

use SquareBracket\Utilities;

$type = ($_GET['type'] ?? 'recent');
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

$limit = $database->paginate($page, 20);

$database = $orange->getDatabaseClass();
$submissions = $database->fetchArray($database->query("SELECT v.* FROM uploads v WHERE v.video_id NOT IN (SELECT submission FROM upload_takedowns) AND v.author = ? ORDER BY v.id DESC $limit", [$auth->getUserID()]));
$submission_count = $database->result("SELECT COUNT(*) FROM uploads u where u.author = ?", [$auth->getUserID()]);

$data = [
    "submissions" => Utilities::makeUploadArray($database, $submissions),
    "count" => $submission_count,
];

echo $twig->render('my_submissions.twig', [
    'data' => $data,
    'page' => $page,
]);
