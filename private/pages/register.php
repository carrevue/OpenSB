<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou
  Copyright (C) 2024 OkayHush

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

global $sb, $twig, $database;

use DateMalformedStringException;
use DateTime;
use BluffingoCore\CoreUtilities;
use Random\RandomException;
use OpenSB\UserFlags;
use OpenSB\Utilities;

if (!$sb->isAccountRegistrationEnabled()) {
    Utilities::notifyBanner("notify_register_disabled", "/");
}

$captcha = $sb->returnCaptchaSettings();

// tip: if youre hosting opensb on a linux distro with selinux included (eg: fedora) and you get some
// kind of access denied error. run this command as root/sudo:
// setsebool -P httpd_can_network_connect on
// -chaziz 4/19/2025

$enableInviteKeys = $sb->isInviteKeysEnabled();

if (isset($_POST['registersubmit'])) {
    $error = "";

    if ($captcha["enabled"]) {
        $verify = curl_init();
        curl_setopt($verify, CURLOPT_URL,   "https://hcaptcha.com/siteverify");
        curl_setopt($verify, CURLOPT_POST, true);
        curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query([
            'secret' => $captcha['secret'],
            'response' => $_POST['h-captcha-response']
        ]));
        curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
        $verify = curl_exec($verify);
        $verify = json_decode($verify, true);

        if (!$verify['success']) {
            $error .= "You must complete the captcha in order to register an account. ";
        }
    }

    $username = trim($_POST['username'] ?? '');
    $pass = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';
    $mail = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $birthdate = $_POST['birthdate'] ?? '';
    if ($enableInviteKeys) {
        $invite = $_POST['invite'];
    }

    $error .= Utilities::validateUsername($username, $database);
    if ($database->result("SELECT COUNT(*) FROM users WHERE email = ?", [$mail]) > 0) $error .= "This email address is used by another account. ";
    if (!isset($pass2) || $pass != $pass2) $error .= "The passwords don't match. ";
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $error .= "Invalid email format. ";

    $isLocalIp = (Utilities::getIpAddress() === "localhost"
        || filter_var(
            Utilities::getIpAddress(),
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false);

    if (!$sb->isInviteKeysEnabled() || !$sb->isDebug()) {
        if (!$isLocalIp) {
            if ($database->result("SELECT COUNT(*) FROM users WHERE ip = ?", [Utilities::getIpAddress()]) >= 2)
                $error .= "Your IP address has too many accounts associated with it. ";
        }
    }

    if ($database->fetch("SELECT COUNT(*) FROM user_old_names WHERE old_name = ?", [$username])["COUNT(*)"] >= 1)
        $error .= "You cannot use someone's previous username. ";

    try {
        $dobDateTime = new DateTime($birthdate);
    } catch (DateMalformedStringException $e) {
        $error .= "You have an invalid birth date. ";
    } finally {
        $currentDate = new DateTime();

        if ($dobDateTime->format('Y') < 1900) {
            $error .= "You have an invalid birth date. ";
        } else {
            $age = $currentDate->diff($dobDateTime)->y;

            if ($age < 13) {
                $error .= "You are below the age of 13. ";
            }
        }
    }

    if ($enableInviteKeys) {
        $inviteValidationResult = $database->result("SELECT id FROM invite_keys WHERE invite_key = ? AND claimed_by IS NULL", [$invite]);
        if (empty($invite) || !$inviteValidationResult) {
            $error .= "Invalid or missing invite key. ";
        }
    }

    if (!$error) {
        $flags = 0;

        if ($sb->isChazizSquareBracketInstance()) {
            $flags |= UserFlags::FLAG_UNVERIFIED->value;
        }

        if ($sb->isFulpTube()) {
            $flags |= UserFlags::FLAG_FULPTUBE_ACCOUNT->value;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (RandomException) {
            Utilities::notifyBanner("notify_token_generation_fail", "/register");
        }

        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
        $database->query(
            "INSERT INTO users (name, password, token, joined, last_seen, title, email, ip, birthdate, flags)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$username, $hashedPassword, $token, time(), time(), $username, $mail, Utilities::getIpAddress(), $dobDateTime->format('Y-m-d'), $flags]
        );
        $userId = $database->insertId();

        if ($enableInviteKeys) {
            $database->query(
                "UPDATE invite_keys SET claimed_by = ?, claimed_time = ? WHERE invite_key = ?",
                [$userId, time(), $invite]
            );
        }

        $_SESSION["SBTOKEN"] = $token;
        $_SESSION["SB_STAFF_AUTHED"] = null; // just to be certain, clear this shit off.

        if ($sb->isDiscordWebhookEnabled()) {
            $data = [
                "username" => $username,
            ];

            $sb->getDiscordWebhookClass()->newUserHook($data);
        }

        CoreUtilities::redirect('./');
    } else {
        Utilities::notifyBanner($error, "/register");
    }
}

$data = [];

if ($captcha['enabled']) {
    $data['captcha_public_token'] = $captcha['public'];
}

echo $twig->render('register.twig', $data);
