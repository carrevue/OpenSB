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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf_token_for_template = $_SESSION['csrf_token'];

// yes Stupid Shit!!!!!!!!!!!!!! Epic!!!!!!! -chaziz 8/23/2024
$logindata = $database->fetch("SELECT admin_password FROM users WHERE name = ?", [$auth->getUserData()["name"]]);

// if this password does not exist. generate it automatically.
if (empty($logindata) || !isset($logindata["admin_password"])) {
    $new_pass = Utilities::generateRandomString(24);
    $database->query("UPDATE users SET admin_password = ? WHERE name = ?", [password_hash($new_pass, PASSWORD_DEFAULT), $auth->getUserData()["name"]]);
    session_regenerate_id(true);
    $_SESSION["SB_STAFF_AUTHED"] = true;
    Utilities::notifyBanner("notify_dashboard_welcome_first_time", "/dashboard/", "success", [$new_pass]);
}

if (isset($_POST["loginsubmit"])) {
    $password = trim($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';
    $error = false;

    if (empty($csrf) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        Utilities::notifyBanner("notify_invalid_csrf", "/dashboard/login");
    }

    if (!$password) $error = true;

    if (!$error) {
        if ($logindata && password_verify($password, $logindata['admin_password'])) {
            session_regenerate_id(true);
            $_SESSION["SB_STAFF_AUTHED"] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
            Utilities::notifyBanner("notify_dashboard_welcome", "/dashboard/", "success");
        } else {
            Utilities::notifyBanner("notify_dashboard_login_incorrect", "/dashboard/login");
        }
    }
}

echo $twig->render('dashboard_login.twig', [
    'csrf_token' => $csrf_token_for_template
]);
