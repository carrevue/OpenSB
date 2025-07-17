<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

global $auth, $twig, $database, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

// yes Stupid Shit!!!!!!!!!!!!!! Epic!!!!!!! -chaziz 8/23/2024
$logindata = $database->fetch("SELECT admin_password FROM users WHERE name = ?", [$auth->getUserData()["name"]]);

// if this password does not exist. generate it automatically.
if (!isset($logindata["admin_password"])) {
    $new_pass = Utilities::generateRandomString(24);
    $database->query("UPDATE users SET admin_password = ? WHERE name = ?", [password_hash($new_pass, PASSWORD_DEFAULT), $auth->getUserData()["name"]]);
    $_SESSION["SB_ADMIN_AUTHED"] = true;
    Utilities::notifyBanner("Welcome! Your admin password is " . $new_pass .
        ". Please note it down in a safe and secure place to avoid losing it.", "/admin/", "success");
}

if (isset($_POST["loginsubmit"])) {
    $error = false;

    $password = ($_POST['password'] ?? null);

    if (!$password) $error = true;

    if (!$error) {
        if ($logindata && password_verify($password, $logindata['admin_password'])) {
            $_SESSION["SB_ADMIN_AUTHED"] = true;
            Utilities::notifyBanner("Welcome to the admin panel.", "/admin/", "success");
        } else {
            Utilities::notifyBanner("Incorrect admin password.", "/admin/login");
        }
    }
}

echo $twig->render('admin_login.twig');
