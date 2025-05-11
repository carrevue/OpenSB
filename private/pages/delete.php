<?php

namespace OpenSB;

global $auth, $orange, $storage, $database;

use SquareBracket\UploadData;
use SquareBracket\Utilities;

// TODO: merge this into my_uploads.php -chaziz 5/10/2025

$id = ($_GET['v'] ?? null);

$submission = new UploadData($orange->getDatabaseClass(), $id);
$data = $submission->getData();

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserID() != $data["author"]) {
    Utilities::notifyBanner("This is not your upload.", "/");
}

$database->query("INSERT INTO upload_deleted (id, uploaded_time, deleted_time) VALUES (?,?,?)", [$id, $data["time"], time()]);
$database->query("DELETE FROM uploads WHERE video_id = ?", [$id]);

$storage->deleteUploadFile($data);

Utilities::notifyBanner("This upload has been successfully deleted.", "/my_uploads", "success");