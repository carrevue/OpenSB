<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $auth, $sb, $database;

use Data\Upload\UploadData;
use Core\Utilities;

// TODO: merge this into my_uploads.php -chaziz 5/10/2025

$id = ($_GET['v'] ?? null);

$upload = new UploadData($sb->getDatabaseClass(), $id);
$data = $upload->getData();

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->getUserID() != $data["author"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

$database->query("INSERT INTO upload_deleted (id, uploaded_time, deleted_time) VALUES (?,?,?)", [$id, $data["timestamp"], time()]);
$database->query("DELETE FROM uploads WHERE upload_id = ?", [$id]);

$auth->bumpLastActive();
$database->query("UPDATE users SET u_index = ? WHERE id = ?", [$auth->getUserData()["u_index"] - 1, $member]);

$sb->getStorageClass()->deleteUploadFile($data);

if (!$database->result("SELECT upload FROM upload_number_history WHERE upload = ? AND date = ?", [$id, date('Y-m-d')])) {
    $database->query("INSERT INTO upload_number_history (upload, date, views, views_raw) VALUES (?,?,?,?)", [$id, date('Y-m-d'), 0, 0]);
} else {
    $database->query("UPDATE upload_number_history set views = ?, views_raw = ? WHERE upload = ? AND date = ?", [0, 0, $id, date('Y-m-d')]);
}

Utilities::notifyBanner("notify_successfully_deleted_upload", "/my_uploads", "success");
