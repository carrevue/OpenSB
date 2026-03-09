<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\CommentData;
use OpenSB\CommentLocation;

include_once('_include.php');

// page-specific shit Here.
if ($sb->getLocalOptions()["skin"] != "finalium") {
    $comments = new CommentData($database, CommentLocation::Profile, $data["id"]);

    $comment_data = $comments->getComments();
    $comment_count = $comments->getCommentCount();
} else {
    $comment_data = [];
    $comment_count = 0;
}

// ???
$page_data = [
    'comments' => $comment_data,
];

if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapSkinProfileGradient($data["userlink_color"]);
}

echo $twig->render("profile_comments.twig", [
    'common' => $common_data,
    'data' => $page_data,
]);
