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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\UploadQuery;
use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "primary", ["Trinium"]);
}

$upload_query = new UploadQuery($sb);

$amount = $_GET["amount"] ?? 24;
$search = trim((string) ($_GET["search"] ?? ""));
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, pp: $amount);

$whereCondition = null;
$params = [];
if ($search !== "") {
    $whereCondition = "(v.title LIKE ? OR v.upload_id LIKE ?)";
    $params = ["%{$search}%", "%{$search}%"];
}

$count = $search !== ""
    ? (int) $database->result("SELECT COUNT(*) FROM uploads v WHERE (v.title LIKE ? OR v.upload_id LIKE ?)", ["%{$search}%", "%{$search}%"])
    : (int) $database->result("SELECT COUNT(*) FROM uploads v");
$uploads = $upload_query->query('v.timestamp DESC', $limit, $whereCondition, $params, true);

$uploads_array = Utilities::makeUploadArray($database, $uploads);

$new_fucking_array = [];

$unique_author_ids = [];

// now figure out the upload status
// temporary for now

// iterate the uploads array First so we can "cache" users
foreach ($uploads_array as $upload) {
    if (isset($upload['author']['id'])) {
        $author_id = $upload['author']['id'];
        if (!array_key_exists($author_id, $unique_author_ids)) {
            // if user is banned then set that shit to true
            $is_banned = (bool) $database->fetch("SELECT * FROM user_bans WHERE user = ?", [$author_id]);
            $unique_author_ids[$author_id] = $is_banned;
        }
    }
}

// iterate the uploads array again
foreach ($uploads_array as $upload) {
    $is_taken_down = $database->fetchArray($database->query("SELECT * FROM upload_takedowns t WHERE t.upload = ?", [$upload["id"]]));

    $upload["status"] = [
        "text" => "Public",
        "color" => "success",
    ];

    if ($upload["flags"]["unprocessed"]) {
        $upload["status"] = [
            "text" => "Unprocessed",
            "color" => "danger",
        ];
    }

    if ($upload["flags"]["block_guests"]) {
        $upload["status"] = [
            "text" => "Public (hidden to guests)",
            "color" => "primary",
        ];
    }

    if ($upload["flags"]["featured"]) {
        $upload["status"] = [
            "text" => "Featured",
            "color" => "warning",
        ];
    }

    if ($is_taken_down) {
        $upload["status"] = [
            "text" => "Taken down",
            "color" => "danger",
        ];
    }

    if (isset($upload["author"]["id"]) && array_key_exists($upload["author"]["id"], $unique_author_ids)) {
        $is_banned = $unique_author_ids[$upload["author"]["id"]];

        if ($is_banned) {
            $upload["status"] = [
                "text" => "Author banned",
                "color" => "danger",
            ];
        }
    }

    $new_fucking_array[] = $upload;
};

echo $twig->render("dashboard_uploads.twig", [
    "uploads" => $new_fucking_array,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
