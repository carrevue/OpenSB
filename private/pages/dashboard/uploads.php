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

namespace OpenSB;

global $auth, $twig, $database, $orange;

use SquareBracket\UploadQuery;
use SquareBracket\Utilities;
use SquareBracket\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

$upload_query = new UploadQuery($database);

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? ""; //TODO
$page = $_GET["page"] ?? 1;

$limit = $database->paginate($page, pp: $amount);

/*
 $count = $database->result(
        "SELECT COUNT(*)
        FROM users u
        WHERE (u.name LIKE CONCAT('%', ?, '%'))
        ", [$search]);
 */

$count = $database->result("SELECT COUNT(*) FROM uploads u");

// kinda fucking stupid i guess but whatever
$uploads = $upload_query->query('v.time DESC', $limit, null, [], true);

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
            $is_banned = (bool) $database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$author_id]);
            $unique_author_ids[$author_id] = $is_banned;
        }
    }
}

// iterate the uploads array again
foreach ($uploads_array as $upload) {
    $is_taken_down = $database->fetchArray($database->query("SELECT * FROM upload_takedowns t WHERE t.submission = ?", [$upload["id"]]));

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
