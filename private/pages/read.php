<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

global $sb, $twig, $database, $auth;

use OpenSB\Utilities;
use OpenSB\CommentData;
use OpenSB\CommentLocation;
use OpenSB\UserCustomizationData;
use OpenSB\UserData;

if ($_GET['j'] ?? null) {
    Utilities::redirect('/read/' . $_GET['j'], 301);
}

$data = $database->fetch("SELECT j.* FROM journals j WHERE j.id = ?", [$id]);

if (!$data) {
    Utilities::notifyBanner("notify_invalid_journal", "/");
}

if ($auth->getUserID() == $data["author"]) {
    $owner = true;
} else {
    $owner = false;
}

if ($sb->isFulpTube() && $data["is_news"]) {
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
    "published" => $data["timestamp"],
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
