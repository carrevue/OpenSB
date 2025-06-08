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

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::notifyBanner("Please change your skin to Biscuit.", "/theme");
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

echo $twig->render("admin_uploads.twig", [
    "uploads" => Utilities::makeUploadArray($database, $uploads),
    "amount" => $amount,
    "page" => $page,
    "count" => $count,
    "search" => $search,
]);