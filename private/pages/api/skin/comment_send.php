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

namespace Pages\SkinAPI;

global $sb, $twig;

use Actions\ActionError;
use Actions\CommentActions;
use Data\Comment\CommentLocation;
use InvalidArgumentException;

header('Content-Type: application/json');

$post_data = json_decode(file_get_contents('php://input'), true);

$apiOutput = [
    "error" => "This request is invalid."
];

if (!isset($post_data['type']) || !isset($post_data['comment'])) {
    echo json_encode($apiOutput);
    exit;
}

try {
    $location = CommentLocation::fromString($post_data['type']);
} catch (InvalidArgumentException $e) {
    echo json_encode(["error" => "Invalid comment type."]);
    exit;
}

$comment = (new CommentActions($sb))->post($location, $post_data["id"] ?? "", $post_data['comment'], (int)($post_data['reply_to'] ?? 0));

if ($comment instanceof ActionError) {
    echo json_encode(["error" => $sb->getLocalizationClass()->translate($comment->value)]);
    exit;
}

// temporary code, will be removed in beta 2. -chaziz 2/28/2026
if ($sb->getCurrentSkinName() == "trinium") {
    $template = 'components/_comment.twig';
} else {
    $template = 'components/comment.twig';
}

$html = $twig->render($template, ['comment' => $comment]);

$apiOutput = [
    "comment" => $comment,
    "html" => $html,
];

echo json_encode($apiOutput);
