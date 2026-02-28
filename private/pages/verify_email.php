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

if (isset($_GET["token"])) {
    $token = $_GET["token"];
    $result = $database->fetch("SELECT * FROM email_verification_token WHERE token = ?", [$token]);

    if ($result) {
        if ($result['expiration'] < time()) {
            $database->query("DELETE FROM email_verification_token WHERE token = ?", [$token]);
            Utilities::notifyBanner("notify_register_email_token_expired", "/");
        } else {
            $database->query("DELETE FROM email_verification_token WHERE token = ?", [$token]);
            $database->query("UPDATE users SET flags = flags & ~? WHERE id = ?", [UserFlags::FLAG_UNVERIFIED->value, $result['user']]);

            if ($sb->isDiscordWebhookEnabled()) {
                $data = [
                    'user' => $result['user'],
                    'author' => "System",
                    'action' => "verified",
                ];

                $sb->getDiscordWebhookClass()->dashboardUserHook($data);
            }

            Utilities::notifyBanner("notify_register_email_token_success", "/", "success");
        }
    } else {
        Utilities::notifyBanner("notify_register_email_token_invalid", "/");
    }
}

if (isset($_POST["loginsubmit"]) || isset($_GET["resend"])) {
    $data = $auth->getUserData();
    $mail = $sb->getMailClass();

    // check cooldown so people dont fuck up the mailer so we dont end up like a CERTAIN other site -chaziz 02/28/2026
    $existing = $database->fetch("SELECT * FROM email_verification_token WHERE user = ?", [$data['id']]);
    if ($existing && (time() - $existing['last_sent']) < 300) {
        Utilities::notifyBanner("notify_register_email_ratelimit", "/verify_email", "warning", [300 / 60]);
    }

    $verification_token = bin2hex(random_bytes(32));
    $expiration = strtotime('+7 days', time());
    $link = Utilities::getURL() . "/verify_email?token=" . $verification_token;

    if ($existing) {
        $database->query(
            "UPDATE email_verification_token SET token = ?, created = ?, expiration = ?, last_sent = ? WHERE user = ?",
            [$verification_token, time(), $expiration, time(), $data['id']]
        );
    } else {
        $database->query(
            "INSERT INTO email_verification_token (user, token, created, expiration, last_sent) VALUES (?, ?, ?, ?, ?)",
            [$data['id'], $verification_token, time(), $expiration, time()]
        );
    }

    $mail->sendVerificationMail($data['email'], $data['name'], $link);
}

echo $twig->render('unverified.twig');