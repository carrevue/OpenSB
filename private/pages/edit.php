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

global $twig, $database, $auth, $sb;

use Data\Upload\UploadData;
use Data\Upload\UploadFlags;
use Core\Utilities;
use Data\Upload\UploadVisibilityEnum;

if (isset($_POST['upload'])) {
    $id = ($_POST['vid_id'] ?? null);
} else {
    $id = ($_GET['v'] ?? null);
}

$upload = new UploadData($database, $id);
$data = $upload->getData();

$flags = $data["flags"];

if (!$auth->isLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->isBanned() || $upload->isTakenDown()) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($auth->getUserID() != $data["author"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (isset($_POST['upload'])) {
    $title = $_POST['title'] ?? null;
    $desc = $_POST['desc'] ?? null;
    $visibility = $_POST['visibility'] ?? "public";

    // visibilty
    $visibility_type = match ($visibility) {
        'private' => UploadVisibilityEnum::Private,
        'unlisted' => UploadVisibilityEnum::Unlisted,
        'public' => UploadVisibilityEnum::Public,
        default => UploadVisibilityEnum::Public,
    };

    $block_guests = $_POST['block_guests'] ?? false;
    $block_comments = $_POST['block_comments'] ?? false;

    if ($block_guests) {
        $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
    } else {
        $flags &= ~UploadFlags::FLAG_BLOCK_GUESTS->value;
    }

    if ($block_comments) {
        $flags |= UploadFlags::FLAG_BLOCK_COMMENTS->value;
    } else {
        $flags &= ~UploadFlags::FLAG_BLOCK_COMMENTS->value;
    }

    if (!empty($_FILES['thumbnail']['name'])) {
        $name = $_FILES['thumbnail']['name'];
        $temp_name = $_FILES['thumbnail']['tmp_name'];
        $ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
        $sb->getStorageClass()->processCustomUploadThumbnail($temp_name, $data["upload_id"]);

        $flags |= UploadFlags::FLAG_CUSTOM_THUMBNAIL->value;
    }

    $database->query(
        "UPDATE uploads SET title = ?, description = ?, flags = ?, visibility = ? WHERE upload_id = ?",
        [$title, $desc, $flags, $visibility_type->value, $id]
    );

    Utilities::notifyBanner("notify_successfully_modified_upload", "/view/" . $id, "success");
}

$infoData = [
    "int_id" => $data["id"],
    "id" => $data["upload_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["timestamp"],
    "type" => $data["type"],
    "visibility" => $data["visibility"],
    "flags" => $upload->getFlagArray(),
];

echo $twig->render('edit.twig', [
    'data' => $infoData,
]);
