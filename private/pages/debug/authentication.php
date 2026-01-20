<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

global $sb;

$auth = $sb->getAuthenticationClass();

$logged = $auth->isUserLoggedIn();
$adult = $auth->isUserOver18();
$stats = $auth->getUserStatData();
$banned = $auth->isBanned();

function trueOrFalse($value) {
    if ($value) {
        echo "True";
    } else {
        echo "False";
    }
}
?>
<h1>Authentication</h1>
<ul>
    <li>Logged in: <?php trueOrFalse($logged); ?></li>
    <li>Banned: <?php trueOrFalse($banned); ?></li>
    <li>Is over 18: <?php trueOrFalse($adult); ?></li>
    <li>Stats: <?php var_dump($stats) ?></li>
</ul>