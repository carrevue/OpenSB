<?php

namespace OpenSB;

global $orange, $twig, $database, $auth;

use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\UserCustomizationData;
use SquareBracket\UserData;
use SquareBracket\Utilities;

$id = ($_GET['j'] ?? null);

$data = $database->fetch("SELECT j.* FROM journals j WHERE j.id = ?", [$id]);

if (!$data) {
    Utilities::notifyBanner("This journal does not exist.", "/");
}

if ($auth->getUserID() == $data["author"]) {
    $owner = true;
} else {
    $owner = false;
}

if ($orange->isFulpTube() && $data["is_site_news"]) {
    $data["title"] = Utilities::replaceSquareBracketWithFulpTube($data["title"]);
    $data["post"] = Utilities::replaceSquareBracketWithFulpTube($data["post"]);
}

$author = new UserData($database, $data["author"]);

$author_userdata_info = $author->getUserArray();

$comments = new CommentData($database, CommentLocation::Journal, $id);

if ($author_userdata_info["flags"]["profile_customization_enabled"]) {
    $profile_color_data = new UserCustomizationData($database, $data["author"]);
} else {
    $profile_color_data = null;
}

$data = [
    "is_owner" => $owner,
    "int_id" => $data["id"],
    "title" => $data["title"],
    "contents" => $data["post"],
    "published" => $data["date"],
    "author" => [
        "id" => $data["author"],
        "info" => $author->getUserArray(),
    ],
    "comments" => $comments->getComments(),
    "customization" => $profile_color_data?->getData() ?? false,
];

echo $twig->render('read_journal.twig', [
    'data' => $data,
]);