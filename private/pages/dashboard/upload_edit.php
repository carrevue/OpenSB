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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb, $path;

use OpenSB\UploadData;
use OpenSB\UploadFlags;
use OpenSB\Utilities;
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

function discord_webhook_notify($sb, $auth, $title, $action, $reason = '')
{
    $data = [
        'title' => $title,
        'author' => $auth->getUserData()["name"],
        'reason' => $reason ?? "",
        'action' => $action,
    ];

    $sb->getDiscordWebhookClass()->dashboardUploadHook($data);
}

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
    $upload_title_for_webhook = $upload->getData()["title"] . " (" . $upload->getData()["upload_id"] . ")";
    $reason = $_POST["reason"] ?? "No reason provided.";

    if ($upload->isTakenDown()) {
        Utilities::notifyBanner("notify_dashboard_upload_takedown_already", $_SERVER['REQUEST_URI']);
    }

    $database->query("
        INSERT INTO upload_takedowns (upload, time, reason, sender)
        VALUES (?,?,?,?);
    ", [$id, time(), $reason, $auth->getUserID()]);

    // ok this shit is very fucking stupid i do not know why is it like this
    discord_webhook_notify($sb, $auth, $upload_title_for_webhook, "takedown", $reason);

    Utilities::notifyBanner("notify_dashboard_upload_takedown_success", $_SERVER['REQUEST_URI'], "success");
}

if (isset($_POST['restore'])) {
    if (!$upload->isTakenDown()) {
        Utilities::notifyBanner("notify_dashboard_upload_restore_failed", $_SERVER['REQUEST_URI']);
    }

    $upload_title_for_webhook = $upload->getData()["title"] . " (" . $upload->getData()["upload_id"] . ")";
    $reason = $takedown[0]["reason"] ?? "No reason provided.";

    $database->query("
        DELETE FROM upload_takedowns
        WHERE upload = ?
    ", [$id]);

    discord_webhook_notify($sb, $auth, $upload_title_for_webhook, "restore", $reason);

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

echo $twig->render("dashboard_upload_edit.twig", [
    'upload' => $page_data,
]);
