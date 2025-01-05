<?php

namespace OpenSB;

global $auth, $orange, $storage, $database;

use SquareBracket\UploadData;
use SquareBracket\Utilities;

$id = ($_GET['v'] ?? null);

$submission = new UploadData($orange->getDatabase(), $id);
$data = $submission->getData();

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserID() != $data["author"]) {
    Utilities::notifyBanner("This is not your upload.", "/");
}

$database->query("DELETE FROM uploads WHERE video_id = ?", [$id]);
$database->query("INSERT INTO deleted_uploads (id, uploaded_time, deleted_time) VALUES (?,?,?)", [$id, $data["time"], time()]);

$storage->deleteUploadFile($data);

Utilities::notifyBanner("This upload has been successfully deleted.", "/my_uploads", "success");