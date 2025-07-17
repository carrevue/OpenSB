<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021-2022 icanttellyou

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

namespace OpenSB;

global $auth, $database;

if (!isset($_POST['subscription']) or $_POST['subscription'] == '') {
    die(); //don't output anything if this sneaky bastard didn't put anything to the comment field
}
if ($database->result("SELECT COUNT(user) FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $_POST['subscription']]) != 0) {
    $database->query("DELETE FROM user_follows WHERE user=? AND id=?", [$auth->getUserID(), $_POST['subscription']]);
    echo "Follow";
} else {
    $database->query("INSERT INTO user_follows (id, user) VALUES (?,?)", [$_POST['subscription'], $auth->getUserID()]);
    echo "Unfollow";
}
