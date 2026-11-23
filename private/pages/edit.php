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

use Core\Utilities;
use Actions\UploadActions;
use Data\Upload\UploadVisibilityEnum;
use Actions\ActionError;

if (isset($_POST['upload'])) {
    $id = ($_POST['vid_id'] ?? null);
} else {
    $id = ($_GET['v'] ?? null);
}

$actions = new UploadActions($sb);

$upload = $actions->checkOwner($id);

if ($upload instanceof ActionError) {
    Utilities::notifyBanner($upload->value, $upload == ActionError::LoginRequired ? "/login" : "/");
}

$data = $upload->getData();

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

    $error = $actions->edit(
            $id,
            $title,
            $desc,
            $visibility_type,
            (bool)($_POST['block_guests'] ?? false),
            (bool)($_POST['block_comments'] ?? false),
            !empty($_FILES['thumbnail']['name']) ? $_FILES['thumbnail']['tmp_name'] : null
        );

    if ($error) {
        Utilities::notifyBanner($error->value, "/");
    }

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
