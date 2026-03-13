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

$sb->getStorageClass()->deleteUploadFile($data);

Utilities::notifyBanner("notify_successfully_deleted_upload", "/my_uploads", "success");
