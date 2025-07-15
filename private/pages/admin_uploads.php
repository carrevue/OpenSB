<?php

namespace OpenSB;

global $auth, $twig, $database, $orange;

use SquareBracket\UploadQuery;
use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

$upload_query = new UploadQuery($database);

$amount = $_GET["amount"] ?? 16;
$search = $_GET["search"] ?? ""; //TODO
$page = $_GET["page"] ?? 1;

$limit = sprintf("%s,%s", (($page - 1) * $amount), $amount);

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

// now figure out the upload status
// temporary for now
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

    $new_fucking_array[] = $upload;
};

echo $twig->render("admin_uploads.twig", [
    "uploads" => $new_fucking_array,
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);
