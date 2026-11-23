<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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

namespace Pages\Dashboard;

global $auth, $twig, $database, $sb, $path;

use Actions\UploadActions;
use Data\Upload\UploadData;
use Data\Upload\UploadFlags;
use Core\Utilities;
use Data\User\UserRoleEnum;

include_once('_include.php');

$actions = new UploadActions($sb);

$upload = new UploadData($database, $id);

$data = $upload->getData();

if (!$data) {
    Utilities::notifyBanner("notify_invalid_upload", "/dashboard/uploads");
}

if ($upload->isTakenDown()) {
    $takedown = $upload->getTakedownData();

    if ($takedown) {
        $takedown_data = $takedown[0];
        $takedown_data["takedownee"] = Utilities::userIDToUsername($database, $takedown[0]["sender"]);
    } else {
        $takedown_data = [];
    }
} else {
    $takedown_data = [];
}

// temporary, will return the user's ban details when that'll be the time -chaziz 03/21/2026
if ($upload->isAuthorBanned()) {
    $author_banned = true;
} else {
    $author_banned = false;
}

$flags = $upload->getFlagArray();

if (isset($_POST['takedown_submit'])) {
    $error = $actions->takedown($id, $_POST["reason"] ?? null);

    if ($error) {
        Utilities::notifyBanner($error->value, $_SERVER['REQUEST_URI']);
    }

    Utilities::notifyBanner("notify_dashboard_upload_takedown_success", $_SERVER['REQUEST_URI'], "success");
}

if (isset($_POST['restore'])) {
    $error = $actions->restore($id);

    if ($error) {
        Utilities::notifyBanner($error->value, $_SERVER['REQUEST_URI']);
    }

    Utilities::notifyBanner("notify_dashboard_upload_restore_success", $_SERVER['REQUEST_URI'], "success");
}

// Update flags
if (isset($_POST['flagsubmit'])) {
    $flags = 0;

    if (isset($_POST['flag_featured'])) {
        $flags |= UploadFlags::FLAG_FEATURED->value;
    }
    if (isset($_POST['flag_unprocessed'])) {
        $flags |= UploadFlags::FLAG_UNPROCESSED->value;
    }
    if (isset($_POST['flag_block_guests'])) {
        $flags |= UploadFlags::FLAG_BLOCK_GUESTS->value;
    }
    if (isset($_POST['flag_block_comments'])) {
        $flags |= UploadFlags::FLAG_BLOCK_COMMENTS->value;
    }
    if (isset($_POST['flag_custom_thumbnail'])) {
        $flags |= UploadFlags::FLAG_CUSTOM_THUMBNAIL->value;
    }
    if (isset($_POST['flag_mature'])) {
        $flags |= UploadFlags::FLAG_MATURE->value;
    }

    $error = $actions->setFlags($id, $flags);

    if ($error) {
        Utilities::notifyBanner($error->value, $_SERVER['REQUEST_URI']);
    }

    Utilities::notifyBanner(
        "notify_successfully_modified_upload",
        "/dashboard/uploads/" . $id,
        "success"
    );
}


if (file_exists(SB_PRIVATE_PATH . "/upload_processor_logs/" . $id . ".log")) {
    $log = file_get_contents(SB_PRIVATE_PATH . "/upload_processor_logs/" . $id . ".log");
} else {
    $log = null;
}

$page_data = [
    "int_id" => $data["id"],
    "id" => $data["upload_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["timestamp"],
    "original_site" => $data["original_site"],
    "published_originally" => $data["original_timestamp"],
    "type" => $data["type"],
    "file" => $data["upload_file"],
    "author" => [
        "id" => $data["author"],
        "info" => $upload->getAuthorData(),
        "banned" => $author_banned,
        //"followers" => $followers,
        //"following" => $followed,
    ],
    "interactions" => [
        "views" => $data["views"],
        "ratings" => $upload->getRatingData(),
        //"favorites" => $favorites,
        //"comments" => $comment_count,
    ],
    //"comments" => $comment_data,
    "flags" => $flags,
    "rating" => $data["rating"],
    //"recommended" => $recommended_upload_array,
    //"other_by_author" => $uploads_by_author_array,
    //"random" => $random_uploads_array,
    //"tags" => $tags,
    "log" => $log,
    "takedown" => $takedown_data,
];

echo $twig->render("upload_edit.twig", [
    'upload' => $page_data,
]);
