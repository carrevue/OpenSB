<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
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

namespace OpenSB;

global $twig, $database, $auth, $orange;

use SquareBracket\Utilities;

$warning = $orange->getWarningString();

$path_username = $path[2] ?? null;

if (isset($path_username)) {
    if (isset($_POST["loginsubmit"])) {
        die("?????");
    }

    $is_the_account_in_the_accounts_array = false;
    $id = Utilities::usernameToUserID($database, $path_username);
    $accounts = $orange->getAccountsArray();
    $new_array = [];
    $token = null;

    // stupid shit
    foreach ($accounts as $account) {
        if ($account["userid"] == $id) {
            if (!$is_the_account_in_the_accounts_array) {
                $is_the_account_in_the_accounts_array = true;
                $token = $account["token"];
                $new_array[] = [
                    "userid" => $auth->getUserID(),
                    "token" => $_SESSION["SBTOKEN"],
                ];
            }
        } else {
            $new_array[] = $account;
        }
    }

    if ($is_the_account_in_the_accounts_array) {
        $_SESSION["SBTOKEN"] = $token;

        $encoded_sbaccounts_cookie = ($warning . base64_encode(json_encode($new_array)));

        setcookie('SBACCOUNTS', $encoded_sbaccounts_cookie, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        Utilities::notifyBanner("notify_login_switched_account", '/', "success", [$path_username]);
    } else {
        Utilities::notifyBanner("You have not logged into this account.", '/');
    }
}

if (isset($_POST["loginsubmit"])) {
    $error = false;

    $username = ($_POST['username'] ?? null);
    $password = ($_POST['password'] ?? null);

    if (!$username) $error = true;
    if (!$password) $error = true;

    if ($auth->isUserLoggedIn() && $username == $auth->getUserData()["name"]) {
        Utilities::notifyBanner("notify_login_same_account", "/");
    }

    if (!$error) {
        $logindata = $database->fetch("SELECT password,token,ip,id,u_flags FROM users WHERE name = ?", [$username]);

        if ($logindata) {
            if ((password_verify($password, $logindata['password']))) {
                if (password_needs_rehash($logindata['password'], PASSWORD_BCRYPT)) {
                    // if the hash's cost value isn't how it should be, rehash it.
                    // (added in preparation for php 8.4) -chaziz 11/2/2024
                    $new_password_hash = password_hash($password, PASSWORD_BCRYPT);

                    $database->query("UPDATE users SET password = ? WHERE id = ?", [$new_password_hash, $logindata['id']]);
                }

                // check if the account is banned (temporary code taken from userdata)
                $isBanned = (bool)$database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$logindata['id']]);

                // check if the account is from an ip that is in ip_bans
                $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [$logindata['ip']]);

                if ($ipban || $isBanned) {
                    Utilities::notifyBanner("notify_login_banned_account", "/login");
                }

                // if we're logged in, add our current token in an array for account switching purposes.
                if (isset($_SESSION["SBTOKEN"])) {
                    if (!isset($_COOKIE["SBACCOUNTS"])) {
                        $current_userid = $auth->getUserID();

                        $cookie_shit_testing[] = [
                            "userid" => $current_userid,
                            "token" => $_SESSION["SBTOKEN"],
                        ];

                        $encoded_sbaccounts_cookie = ($warning . base64_encode(json_encode($cookie_shit_testing)));
                    } else {
                        // TODO: this will be buggy, i can feel it. -chaziz 6/28/2024
                        // FIXME: and yes it is! duplicate accounts. i kinda dont care tho. -chaziz 8/23/2024
                        $stupid_fucking_bullshit = str_replace($warning, "", $_COOKIE["SBACCOUNTS"]);
                        $decoded_accounts = json_decode(base64_decode($stupid_fucking_bullshit), true);

                        $current_userid = $auth->getUserID();

                        $duplicates = array_keys(array_combine(array_keys($decoded_accounts), array_column($decoded_accounts, 'userid')), $logindata["id"]);

                        foreach ($duplicates as $duplicate) {
                            unset($decoded_accounts[$duplicate]);
                        }

                        if ($current_userid != $logindata["id"]) {
                            $decoded_accounts[] = [
                                "userid" => $current_userid,
                                "token" => $_SESSION["SBTOKEN"],
                            ];
                        }

                        $encoded_sbaccounts_cookie = ($warning . base64_encode(json_encode($decoded_accounts)));
                    }

                    setcookie('SBACCOUNTS', $encoded_sbaccounts_cookie, [
                        'expires' => time() + (30 * 24 * 60 * 60),
                        'path' => '/',
                        'domain' => '',
                        'secure' => isset($_SERVER['HTTPS']),
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);

                    // null access to admin panel for security
                    $_SESSION["SB_STAFF_AUTHED"] = null;
                }

                $_SESSION["SBTOKEN"] = $logindata['token'];

                $nid = $database->result("SELECT id FROM users WHERE token = ?", [$logindata['token']]);
                $database->query("UPDATE users SET lastview = ?, ip = ? WHERE id = ?", [time(), Utilities::getIpAddress(), $nid]);

                Utilities::redirect('./');
            } else {
                Utilities::notifyBanner("notify_login_incorrect", "/login");
            }
        } else {
            Utilities::notifyBanner("There is no account with these credentials.", "/login");
        }
    } else {
        Utilities::notifyBanner("notify_login_invalid", "/login");
    }
}

echo $twig->render('login.twig');
