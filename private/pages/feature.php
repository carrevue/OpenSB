<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

global $database, $auth;

// TODO: merge this into my_uploads.php -chaziz 5/10/2025

use OpenSB\UploadData;
use OpenSB\Utilities;

$id = ($_GET['v'] ?? null);

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

$submission = new UploadData($database, $id);

if (!$id) {
    Utilities::notifyBanner("You have not specified the upload.", "/");
}

if ($auth->getUserBanData() || $submission->getTakedown()) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

$data = $submission->getData();

if (!$data) {
    Utilities::notifyBanner("notify_invalid_upload", "/");
}

if (!$auth->getUserID() == $data["author"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($database->query(
    "UPDATE users SET featured_submission = ? WHERE id = ?",
    [$data["id"], $auth->getUserID()]
)) {
    Utilities::notifyBanner("notify_updated_featured_upload", "/user?name=" . $auth->getUserData()["name"], "success");
}
