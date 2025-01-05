<?php

namespace OpenSB;

global $database, $auth;

// NOTE: this page is unused and looks as if its doing nothing to the end-user.
// originally, profiles on biscuit frontend used to show the user's "featured" upload, which was
// removed around mid-2024 due to a redesign of profiles. this *may* comeback if i redesign profiles
// on the charla frontend. -chaziz 1/4/2025

use SquareBracket\Utilities;

$id = ($_GET['v'] ?? null);

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

$submission = new \SquareBracket\UploadData($database, $id);

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