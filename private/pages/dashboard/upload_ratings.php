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

namespace Pages\Dashboard;

global $auth, $twig, $database, $sb;

use Data\Upload\UploadData;
use Data\User\UserData;
use Core\Utilities;
use Data\User\UserRoleEnum;

include_once('_include.php');

function handle_ratings($raw_data, $database) {
    $array = [];

    foreach ($raw_data as $value) {
        $uid = $value["user"];
        $user_data = new UserData($database, $uid);

        $array[$uid]["banned"] = $user_data->isUserBanned();
        $array[$uid]["info"] = $user_data->getUserArray();
        $array[$uid]["rating"] = $value["rating"];
    }
    return $array;
}

$upload = new UploadData($database, $id);
$data = $upload->getData();

$ratings = $database->fetchArray($database->query("SELECT * FROM upload_ratings WHERE upload = ?", [$upload->getData()['id']]));

echo $twig->render("upload_ratings.twig", [
    'upload' => $data,
    'ratings' => handle_ratings($ratings, $database),
]);