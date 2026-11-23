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
use Actions\JournalActions;
use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$username = fn($id) => htmlspecialchars(Utilities::userIDToUsername($database, (int)$id) ?: "#$id");

$actions = new JournalActions($sb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $actions->create($_POST['title'] ?? '', $_POST['post'] ?? '', isset($_POST['news']));

    if ($result instanceof ActionError) {
        die($sb->getLocalizationClass()->translate($result->value));
    }

    Utilities::redirect('/debug/journals');
}

$canWrite = $actions->canWrite();

$journals = $database->fetchArray($database->query("SELECT * FROM journals ORDER BY timestamp DESC LIMIT 100"));
?>
<h1>Journals</h1>
<p>Logged in as <?= $auth->isLoggedIn() ? $username($auth->getUserID()) : 'nobody' ?></p>
<p>Can write: <?= $canWrite instanceof ActionError ? htmlspecialchars($sb->getLocalizationClass()->translate($canWrite->value)) : 'yes' ?></p>

<form method="post">
    <input type="text" name="title" placeholder="title">
    <input type="text" name="post" placeholder="post">
    <label><input type="checkbox" name="news"> news</label>
    <input type="submit" value="Post">
</form>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Post</th>
        <th>Author</th>
        <th>News</th>
        <th>Posted</th>
    </tr>
    <?php foreach ($journals as $journal): ?>
        <tr>
            <td><?= (int)$journal['id'] ?></td>
            <td><?= htmlspecialchars($journal['title']) ?></td>
            <td><?= htmlspecialchars($journal['post']) ?></td>
            <td><?= $username($journal['author']) ?></td>
            <td><?= $journal['is_news'] ? 'yes' : '' ?></td>
            <td><?= date('Y-m-d H:i:s', (int)$journal['timestamp']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
