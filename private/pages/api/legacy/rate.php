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

namespace OpenSB\Pages\LegacySkinAPI;

global $auth, $database;

if (!isset($_POST['vidid'])) {
    die("No POST data.");
} else if (!isset($_POST['rating']) or $_POST['rating'] == '') {
    die(); //don't output anything if there is no data.
}
if ($database->result("SELECT COUNT(rating) FROM upload_ratings WHERE upload=? AND user=?", [$database->result("SELECT id FROM uploads WHERE upload_id=?", [$_POST['vidid']]), $auth->getUserID()]) != 0) {
    $database->query(
        "DELETE FROM upload_ratings WHERE user=? AND upload=?",
        [$auth->getUserID(), $database->result("SELECT id FROM uploads WHERE upload_id=?", [$_POST['vidid']])]
    );
    echo 0;
} else {
    $database->query(
        "INSERT INTO upload_ratings (user, upload, rating) VALUES (?,?,?)",
        [$auth->getUserID(), $database->result("SELECT id FROM uploads WHERE upload_id=?", [$_POST['vidid']]), $_POST['rating']]
    );
    echo 1;
}
