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

namespace OpenSB\Pages;

global $twig, $database, $sb, $auth;

use OpenSB\UserFlags;
use OpenSB\Utilities;

if (!$auth->isUserLoggedIn()) { //|| !($auth->getUserFlags() & UserFlags::FLAG_UNVERIFIED->value)) {
    Utilities::redirect('/');
}

if (isset($_POST["loginsubmit"]) || isset($_GET["resend"])) {
    $data = $auth->getUserData();
    $mail = $sb->getMailClass();
    $mail->sendVerificationMail($data["email"], $data["name"], "https://example.com/ (This is a testing email)");
}

echo $twig->render('unverified.twig');