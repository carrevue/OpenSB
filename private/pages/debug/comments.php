<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace Pages\Debug;

global $sb, $database, $auth;

use Actions\ActionError;
use Actions\CommentActions;
use Core\Utilities;
use Data\Comment\CommentLocation;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$username = fn($id) => htmlspecialchars(Utilities::userIDToUsername($database, (int)$id) ?: "#$id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = (new CommentActions($sb))->post(
        CommentLocation::fromString($_POST['type'] ?? 'upload'),
        $_POST['id'] ?? '',
        $_POST['comment'] ?? '',
        (int)($_POST['reply_to'] ?? 0)
    );

    if ($result instanceof ActionError) {
        die($sb->getLocalizationClass()->translate($result->value));
    }

    Utilities::redirect('/debug/comments');
}

$comments = $database->fetchArray($database->query(
    "(SELECT 'upload' AS type, id, location_id, reply_to, comment, author, timestamp FROM upload_comments)
     UNION ALL
     (SELECT 'profile', id, location_id, reply_to, comment, author, timestamp FROM user_profile_comments)
     UNION ALL
     (SELECT 'journal', id, location_id, reply_to, comment, author, timestamp FROM journal_comments)
     ORDER BY timestamp DESC
     LIMIT 100"
));
?>
<h1>Comments</h1>
<p>Logged in as <?= $auth->isLoggedIn() ? $username($auth->getUserID()) : 'nobody' ?></p>

<form method="post">
    <select name="type">
        <option value="upload">upload</option>
        <option value="profile">profile</option>
        <option value="journal">journal</option>
    </select>
    <input type="text" name="id" placeholder="id">
    <input type="number" name="reply_to" placeholder="reply to" min="0">
    <input type="text" name="comment" placeholder="comment">
    <input type="submit" value="Post">
</form>

<table border="1">
    <tr>
        <th>Type</th>
        <th>ID</th>
        <th>Location</th>
        <th>Reply to</th>
        <th>Author</th>
        <th>Comment</th>
        <th>Posted</th>
    </tr>
    <?php foreach ($comments as $comment): ?>
        <tr>
            <td><?= $comment['type'] ?></td>
            <td><?= (int)$comment['id'] ?></td>
            <td><?= htmlspecialchars($comment['location_id']) ?></td>
            <td><?= (int)$comment['reply_to'] ?: '' ?></td>
            <td><?= $username($comment['author']) ?></td>
            <td><?= htmlspecialchars($comment['comment']) ?></td>
            <td><?= date('Y-m-d H:i:s', (int)$comment['timestamp']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
