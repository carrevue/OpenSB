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
use Actions\UserActions;
use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$username = fn($id) => htmlspecialchars(Utilities::userIDToUsername($database, (int)$id) ?: "#$id");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member = Utilities::usernameToUserID($database, $_POST['user'] ?? '');

    if (!$member) {
        die($sb->getLocalizationClass()->translate(ActionError::InvalidUser->value));
    }

    $result = (new UserActions($sb))->toggleFollow((int)$member);

    if ($result instanceof ActionError) {
        die($sb->getLocalizationClass()->translate($result->value));
    }

    Utilities::redirect('/debug/follows');
}

$follows = $database->fetchArray($database->query("SELECT id, user FROM user_follows"));

$users = $database->fetchArray($database->query(
    "SELECT u.id, u.name, u.f_index, (SELECT COUNT(*) FROM user_follows f WHERE f.id = u.id) AS actual
     FROM users u
     WHERE u.f_index > 0 OR EXISTS (SELECT 1 FROM user_follows f WHERE f.id = u.id OR f.user = u.id)
     ORDER BY u.name"
));
?>
<h1>Follows</h1>
<p>Logged in as <?= $auth->isLoggedIn() ? $username($auth->getUserID()) : 'nobody' ?></p>

<form method="post">
    <input type="text" name="user" placeholder="username">
    <input type="submit" value="Follow/unfollow">
</form>

<table border="1">
    <tr>
        <th>Follower</th>
        <th>Following</th>
    </tr>
    <?php foreach ($follows as $follow): ?>
        <tr>
            <td><?= $username($follow['user']) ?></td>
            <td><?= $username($follow['id']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>

<table border="1">
    <tr>
        <th>User</th>
        <th>f_index</th>
        <th>Followers</th>
    </tr>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['name']) ?></td>
            <td><?= (int)$user['f_index'] ?></td>
            <td><?= (int)$user['actual'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>
