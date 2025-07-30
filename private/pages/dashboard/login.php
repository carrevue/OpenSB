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
use SquareBracket\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

// yes Stupid Shit!!!!!!!!!!!!!! Epic!!!!!!! -chaziz 8/23/2024
$logindata = $database->fetch("SELECT admin_password FROM users WHERE name = ?", [$auth->getUserData()["name"]]);

// if this password does not exist. generate it automatically.
if (!isset($logindata["admin_password"])) {
    $new_pass = Utilities::generateRandomString(24);
    $database->query("UPDATE users SET admin_password = ? WHERE name = ?", [password_hash($new_pass, PASSWORD_DEFAULT), $auth->getUserData()["name"]]);
    $_SESSION["SB_STAFF_AUTHED"] = true;
    Utilities::notifyBanner("notify_dashboard_welcome_first_time", "/dashboard/", "success", [$new_pass]);
}

if (isset($_POST["loginsubmit"])) {
    $error = false;

    $password = ($_POST['password'] ?? null);

    if (!$password) $error = true;

    if (!$error) {
        if ($logindata && password_verify($password, $logindata['admin_password'])) {
            $_SESSION["SB_STAFF_AUTHED"] = true;
            Utilities::notifyBanner("notify_dashboard_welcome", "/dashboard/", "success");
        } else {
            Utilities::notifyBanner("notify_dashboard_login_incorrect", "/dashboard/login");
        }
    }
}

echo $twig->render('dashboard_login.twig');
