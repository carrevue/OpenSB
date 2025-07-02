<?php

namespace OpenSB;

global $database, $auth;

// TODO: merge this into my_uploads.php -chaziz 5/10/2025

use SquareBracket\UploadData;
use SquareBracket\Utilities;

$id = ($_GET['v'] ?? null);

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

$submission = new UploadData($database, $id);

if (!$id) {
    Utilities::notifyBanner("You have not specified the upload.", "/");
}

if ($auth->getUserBanData() || $submission->getTakedown()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

$data = $submission->getData();

if (!$data) {
    Utilities::notifyBanner("This upload does not exist.", "/");
}

if (!$auth->getUserID() == $data["author"]) {
    Utilities::notifyBanner("This is not your upload.", "/");
}

if ($database->query("UPDATE users SET featured_submission = ? WHERE id = ?",
    [$data["id"], $auth->getUserID()])) {
    Utilities::notifyBanner("You have successfully changed your profile's featured upload.", "/user?name=" . $auth->getUserData()["name"], "success");
}