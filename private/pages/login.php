<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
  Copyright (C) 2021 ROllerozxa
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

namespace Pages;

global $twig, $database, $auth, $sb;

use Data\Account\AccountFlags;
use Data\User\UserRoleEnum;
use Core\Utilities;

$warning = $auth->getWarningString();

function setAccUser($user_id, $account_token, $warning) {
    session_regenerate_id(true);
    $_SESSION["SB_STAFF_AUTHED"] = null;

    $cookie = [
        "user_id" => $user_id,
        "token" => $account_token,
    ];

    // TODO: redo this. this is ass. -chaziz 05/17/2026
    $signed = Utilities::makeSignedCookiePayload($cookie);
    Utilities::setSafeCookie('SBAUTH', $warning . $signed, time() + (30 * 24 * 60 * 60));

    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}

$max_attempts = 8;
$attempt_window = 300; // 5 minutes

if (time() - $_SESSION['login_attempts']['first'] > $attempt_window) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}

if (isset($user)) {
    if (!$auth->isLoggedIn()) {
        Utilities::redirect('./');
    }

    if ($user == $auth->getUserData()["name"]) {
        Utilities::notifyBanner("notify_login_same_account", "/");
    }

    // get user id
    $uid = Utilities::usernameToUserID($database, $user);

    // check if we have access
    $permission = $database->result("SELECT user FROM account_user_roles WHERE account = ? AND user = ?", [$auth->getAccountID(), $uid]);

    if ($permission) {
        setAccUser($uid, $auth->getAccountData()["token"], $warning);
    }

    Utilities::redirect(Utilities::getURL());
}

if (isset($_POST["loginsubmit"])) {
    $error = false;

    if ($_SESSION['login_attempts']['count'] >= $max_attempts) {
        Utilities::notifyBanner("notify_login_too_many_attempts", '/login');
        $error = true;
    }

    $posted_csrf = $_POST['csrf_token'] ?? null;
    if (!$posted_csrf || !hash_equals($_SESSION['csrf_token'], $posted_csrf)) {
        Utilities::notifyBanner("notify_invalid_csrf", '/login');
        $error = true;
    }

    // TEMPORARY !!!!!!!!!! -chaziz 05/16/2026
    $email = (isset($_POST['email']) ? trim($_POST['email']) : null);
    $password = (isset($_POST['password']) ? $_POST['password'] : null);

    if (!$email) $error = true;
    if (!$password) $error = true;

    if ($auth->isLoggedIn() && $email == $auth->getUserData()["name"]) {
        Utilities::notifyBanner("notify_login_same_account", "/");
    }

    if (!$error) {
        $acc_logindata = $database->fetch("SELECT password, token, ip, id, flags FROM accounts WHERE email = ?", [$email]);

        // get the first user of an account, this is temporary and will be fixed soon.
        $oh_god_temporary_hack = $database->result("SELECT user FROM account_user_roles WHERE account = ? LIMIT 1", [$acc_logindata["id"]]);

        $logindata = $database->fetch("SELECT ip, id, powerlevel FROM users WHERE id = ?", [$oh_god_temporary_hack]);

        if ($logindata) {
            if (
                $sb->isTestInstance()
                && $logindata['powerlevel'] < UserRoleEnum::Moderator->value
                && !($acc_logindata['flags'] & AccountFlags::FLAG_QA_ACCESS->value)
            ) {
                Utilities::notifyBanner("notify_login_test_instance", "/login");
            }

            if ((password_verify($password, $acc_logindata['password']))) {
                if (password_needs_rehash($acc_logindata['password'], PASSWORD_BCRYPT)) {
                    $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $database->query("UPDATE accounts SET password = ? WHERE id = ?", [$new_password_hash, $acc_logindata['id']]);
                }

                /*
                $isBanned = (bool)$database->fetch("SELECT * FROM user_bans WHERE user = ?", [$logindata['id']]);
                $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [$logindata['ip']]);

                if ($ipban || $isBanned) {
                    Utilities::notifyBanner("notify_login_banned_account", "/login");
                    $_SESSION['login_attempts']['count']++;
                    $error = true;
                }
                */

                if (!$error) {
                    setAccUser($oh_god_temporary_hack, $acc_logindata["token"], $warning);

                    Utilities::redirect('./');
                }
            } else {
                $_SESSION['login_attempts']['count']++;
                Utilities::notifyBanner("notify_login_incorrect", "/login");
            }
        } else {
            $_SESSION['login_attempts']['count']++;
            Utilities::notifyBanner("notify_login_no_account", "/login");
        }
    } else {
        if (!isset($_SESSION['login_attempts']['count'])) $_SESSION['login_attempts']['count'] = 0;
        $_SESSION['login_attempts']['count']++;
        Utilities::notifyBanner("notify_login_invalid", "/login");
    }
}

echo $twig->render('login.twig');
