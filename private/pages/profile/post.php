<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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
use OpenSB\JournalData;
use OpenSB\UserCustomizationData;
use OpenSB\UserRoleEnum;

include_once('_include.php');

if ($_GET['j'] ?? null) {
    Utilities::redirect('/read/' . $_GET['j'], 301);
}

$journal = new JournalData($database, $id);

// check if the journal has been taken down.
if ($journal->isTakenDown() && !$auth->userHasRole(UserRoleEnum::Moderator)) {
    // go back to homepage with a notification
    Utilities::notifyBanner("notify_taken_down_upload", "/"); // TODO
}

$data = $journal->getData();
if (!$data) {
    Utilities::notifyBanner("notify_invalid_journal", "/");
}

$owner = ($auth->getUserID() == $data["author"]);

if ($sb->isFulpTubeMode() && $data["is_news"]) {
    $data["title"] = Utilities::replaceSquareBracketWithFulpTube($data["title"]);
    $data["post"] = Utilities::replaceSquareBracketWithFulpTube($data["post"]);
}

$author_info = $journal->getAuthorData();

if ($sb->getLocalOptions()["skin"] != "finalium") {
    $comments = new CommentData($database, CommentLocation::Journal, $id);

    $comment_data = $comments->getComments();
    $comment_count = $comments->getCommentCount();
} else {
    $comment_data = [];
    $comment_count = 0;
}

if ($author_info["flags"]["profile_customization_enabled"]) {
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
        "info" => $author_info,
    ],
    "comments" => $comment_data,
    "customization" => $profile_color_data?->getData() ?? false,
];

echo $twig->render('profile_post.twig', [
    'common' => $common_data,
    'data' => $data,
]);
