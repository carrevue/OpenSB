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

namespace OpenSB\Pages;

global $twig, $database, $auth, $sb;

use OpenSB\UserRoleEnum;
use OpenSB\Utilities;

$warning = $sb->getWarningString();

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}
$max_attempts = 8;
$attempt_window = 300; // 5 minutes
if (time() - $_SESSION['login_attempts']['first'] > $attempt_window) {
    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];
}

if (isset($user)) {
    if ($auth->isUserLoggedIn() && $user == $auth->getUserData()["name"]) {
        Utilities::notifyBanner("notify_login_same_account", "/");
    }

    $is_the_account_in_the_accounts_array = false;
    $id = Utilities::usernameToUserID($database, $user);
    $accounts = $sb->getAccountsArray();
    $new_array = [];
    $token = null;

    foreach ($accounts as $account) {
        if (!isset($account['userid'], $account['token'])) continue;
        if ($account['userid'] == $id) {
            if (!$is_the_account_in_the_accounts_array) {
                $is_the_account_in_the_accounts_array = true;
                $token = $account['token'];
                $new_array[] = [
                    'userid' => $auth->getUserID(),
                    'token' => $_SESSION['SBTOKEN'] ?? null,
                ];
            }
        } else {
            $new_array[] = $account;
        }
    }

    if ($is_the_account_in_the_accounts_array) {
        $_SESSION["SBTOKEN"] = $token;
        $_SESSION["SB_STAFF_AUTHED"] = null;

        $signed = Utilities::makeSignedCookiePayload($new_array);
        Utilities::setSafeCookie('SBACCOUNTS', $warning . $signed, time() + (30 * 24 * 60 * 60));

        Utilities::notifyBanner("notify_login_switched_account", '/', "success", [$user]);
    } else {
        Utilities::notifyBanner("You are not logged into this account.", '/login');
    }
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

    $username = (isset($_POST['username']) ? trim($_POST['username']) : null);
    $password = (isset($_POST['password']) ? $_POST['password'] : null);

    if (!$username) $error = true;
    if (!$password) $error = true;

    if ($auth->isUserLoggedIn() && $username == $auth->getUserData()["name"]) {
        Utilities::notifyBanner("notify_login_same_account", "/");
    }

    if (!$error) {
        $logindata = $database->fetch("SELECT password,token,ip,id,flags,powerlevel FROM users WHERE name = ?", [$username]);

        if ($logindata) {
            if ($sb->isTestInstance()) {
                if ($logindata['powerlevel'] < UserRoleEnum::Moderator->value) {
                    Utilities::notifyBanner("notify_login_test_instance", "/login");
                }
            }

            if ((password_verify($password, $logindata['password']))) {
                if (password_needs_rehash($logindata['password'], PASSWORD_BCRYPT)) {
                    $new_password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $database->query("UPDATE users SET password = ? WHERE id = ?", [$new_password_hash, $logindata['id']]);
                }

                $isBanned = (bool)$database->fetch("SELECT * FROM user_bans WHERE user = ?", [$logindata['id']]);
                $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [$logindata['ip']]);

                if ($ipban || $isBanned) {
                    Utilities::notifyBanner("notify_login_banned_account", "/login");
                    $_SESSION['login_attempts']['count']++;
                    $error = true;
                }

                if (!$error) {
                    if (isset($_COOKIE['SBACCOUNTS'])) {
                        $raw = $_COOKIE['SBACCOUNTS'];
                        if (strpos($raw, $warning) === 0) {
                            $raw = substr($raw, strlen($warning));
                        }
                        $decoded_accounts = Utilities::verifySignedCookiePayload($raw);
                        if ($decoded_accounts === false) {
                            $decoded_accounts = [];
                        }
                        $safe_accounts = [];
                        foreach ($decoded_accounts as $entry) {
                            if (!is_array($entry)) continue;
                            if (!isset($entry['userid'], $entry['token'])) continue;
                            if (!ctype_digit((string)$entry['userid'])) continue;
                            $uid = (int)$entry['userid'];
                            $tok = (string)$entry['token'];
                            if (strlen($tok) < 10) continue;
                            $already = false;
                            foreach ($safe_accounts as $sa) {
                                if ($sa['userid'] === $uid) {
                                    $already = true;
                                    break;
                                }
                            }
                            if (!$already) $safe_accounts[] = ['userid' => $uid, 'token' => $tok];
                        }

                        $current_userid = $auth->getUserID();
                        if ($current_userid && ($current_userid !== (int)$logindata['id'])) {
                            $safe_accounts[] = [
                                'userid' => $current_userid,
                                'token' => $_SESSION['SBTOKEN'],
                            ];
                        }

                        $signed = Utilities::makeSignedCookiePayload($safe_accounts);
                        Utilities::setSafeCookie('SBACCOUNTS', $warning . $signed, time() + (30 * 24 * 60 * 60));

                        session_regenerate_id(true);
                        $_SESSION["SBTOKEN"] = $logindata['token'];
                        $_SESSION["SB_STAFF_AUTHED"] = null;

                        $nid = $database->result("SELECT id FROM users WHERE token = ?", [$logindata['token']]);
                        $database->query("UPDATE users SET last_seen = ?, ip = ? WHERE id = ?", [time(), Utilities::getIpAddress(), $nid]);
                    } else {
                        $payload = [['userid' => $auth->getUserID(), 'token' => $_SESSION['SBTOKEN']]];
                        $signed = Utilities::makeSignedCookiePayload($payload);
                        Utilities::setSafeCookie('SBACCOUNTS', $warning . $signed, time() + (30 * 24 * 60 * 60));
                    }

                    $_SESSION['login_attempts'] = ['count' => 0, 'first' => time()];

                    Utilities::redirect('./');
                }
            } else {
                $_SESSION['login_attempts']['count']++;
                Utilities::notifyBanner("notify_login_incorrect", "/login");
            }
        } else {
            $_SESSION['login_attempts']['count']++;
            Utilities::notifyBanner("notify_login_invalid", "/login");
        }
    } else {
        if (!isset($_SESSION['login_attempts']['count'])) $_SESSION['login_attempts']['count'] = 0;
        $_SESSION['login_attempts']['count']++;
        Utilities::notifyBanner("notify_login_invalid", "/login");
    }
}

echo $twig->render('login.twig');
