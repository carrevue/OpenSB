<?php

namespace OpenSB;

global $auth, $twig, $database, $orange, $path;

use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::bannerNotification("You do not have permission to access this page.", "/");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::bannerNotification("Please change your skin to Biscuit.", "/theme");
}

// yes Stupid Shit!!!!!!!!!!!!!! Epic!!!!!!! -chaziz 8/23/2024
$logindata = $database->fetch("SELECT admin_password FROM users WHERE name = ?", [$auth->getUserData()["name"]]);

// if this password does not exist. generate it automatically.
if (!isset($logindata["admin_password"])) {
    $new_pass = Utilities::generateRandomString(24);
    $database->query("UPDATE users SET admin_password = ? WHERE name = ?", [password_hash($new_pass, PASSWORD_DEFAULT), $auth->getUserData()["name"]]);
    $_SESSION["SB_ADMIN_AUTHED"] = true;
    Utilities::bannerNotification("Welcome! Your admin password is " . $new_pass .
        ". Please note it down in a safe and secure place to avoid losing it.", "/admin/", "success");
}

if (isset($_POST["loginsubmit"])) {
    $error = false;

    $password = ($_POST['password'] ?? null);

    if (!$password) $error = true;

    if (!$error) {
        if ($logindata && password_verify($password, $logindata['admin_password'])) {
            $_SESSION["SB_ADMIN_AUTHED"] = true;
            Utilities::bannerNotification("Welcome to the admin panel.", "/admin/", "success");
        } else {
            Utilities::bannerNotification("Incorrect admin password.", "/admin/login");
        }
    }
}

echo $twig->render('admin_login.twig');