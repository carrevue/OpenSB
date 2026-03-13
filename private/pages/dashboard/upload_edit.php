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

global $auth, $twig, $database, $sb, $path;

use OpenSB\UploadData;
use OpenSB\UploadFlags;
use Core\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}

$upload = new UploadData($database, $id);

$data = $upload->getData();

if (!$data) {
    Utilities::notifyBanner("notify_invalid_upload", "/dashboard/uploads");
}

$flags = $upload->getFlagArray();

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

    $database->query(
        "UPDATE uploads SET flags = ? WHERE upload_id = ?",
        [$flags, $id]
    );
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
];

echo $twig->render("dashboard_upload_edit.twig", [
    'upload' => $page_data,
]);
