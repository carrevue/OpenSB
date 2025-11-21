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

namespace OpenSB\Pages\BotAPI;

global $sb;

header('Content-Type: application/json');

var_dump(getallheaders());

// SBTOKEN isn't going to suffice.

//if (!$_SERVER['HTTP_AUTHORIZATION']) { (dogshit)
if (!$token = getallheaders()["Authorization"]) {
    http_response_code(403);
    echo json_encode(['status' => 'Access denied.']);
    die();
}

$sb->logInWithToken($token);

$apiOutput = [
    'status' => ["success"],
];

echo json_encode($apiOutput);
