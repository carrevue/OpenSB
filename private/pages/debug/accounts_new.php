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

namespace OpenSB\Pages\Debug;

global $sb, $database;

$data = $database->fetchArray($database->query("SELECT accounts.*, GROUP_CONCAT(users.name SEPARATOR ', ') AS users
FROM accounts
LEFT JOIN account_user_roles ON account_user_roles.account = accounts.id
LEFT JOIN users ON users.id = account_user_roles.user
GROUP BY accounts.id"));
?>

<h1>Accounts <span style="color:gold;">NEW!</span></h1>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Email</th>
        <th>Registered</th>
        <th>Users</th>
    </tr>
    <?php foreach ($data as $n): ?>
        <tr>
            <td><?= $n['id'] ?></td>
            <td><?= $n['email'] ?></td>
            <td><?= date('Y-m-d H:i:s', $n['registered']) ?></td>
            <td><?= $n['users'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>