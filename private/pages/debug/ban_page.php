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

global $twig, $sb;

use Data\User\FakeUser;

if (isset($_POST['submit'])) {
    $auth = $sb->getAuthenticationClass();
    if ($auth->isUserLoggedIn()) {
        $userData = $auth->getUserData();
    } else {
        $userData = FakeUser::getFakeUserFromID(-1000);
    }

    $banTimestamp = strtotime($_POST['ban-time']) ?: 0;
    $expirationTimestamp = !empty($_POST['expiration'])
        ? strtotime($_POST['expiration'])
        : null;

    $banData = [
        'reason'     => 'Debug ban preview',
        'timestamp'  => $banTimestamp,
        'expiration' => $expirationTimestamp,
        'user_data'  => $userData, // override Templating's userdata
    ];

    echo $twig->render('banned.twig', $banData);
    exit;
}

?>
<h1>Ban pageifier!</h1>
<form action="/debug/ban_page" method="post">
    <!--<div>
        <label for="skin_theme">Skin/Theme:</label>
        <select type="text" id="skin_theme" name="skin_theme" required></select>
    </div>-->

    <div>
        <label for="ban-time">Ban time:</label>
        <input type="datetime-local" id="ban-time" name="ban-time" required> 
    </div>

    <div>
        <label for="expiration">Expiration:</label>
        <input type="datetime-local" id="expiration" name="expiration"> 
    </div>

    <p style="color: red;">this does NOT ban you</p>
    <div>
        <input type="submit" name="submit" value="Ill show you...">
    </div>
</form>