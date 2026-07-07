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

global $sb, $auth;

use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

if (!$auth->isLoggedIn()) {
    die("NOT LOGGED IN");
}
?>
<h1>Claim a channel</h1>
<p>If you have a channel made before Mayvember 32th 5259, you can claim it here. It will be linked to your <?php echo $sb->getBrandingSettings()["name"]; ?> account <?php echo $auth->getAccountData()["email"]; ?>.</p>
<p>This does not include accounts made on the original FulpTube during 2020-2021.</p>
<form action="/debug/claim" method="post">
    <fieldset>
        <legend>Channel</legend>
        <label for="name">Username:</label>
        <input type="text" id="name" name="name" maxlength="128" required><br><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" maxlength="128" required><br><br>
    </fieldset>

    <input type="submit" name="submit" value="Claim channel">
</form>