<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Pages\Debug;

global $sb, $database;

use DateMalformedStringException;
use DateTime;
use Random\RandomException;
use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

if ($sb->isIpLookupEnabled()) {
    $ipInfo = $sb->getIpLookupClass()->getInfo(Utilities::getIpAddress());
}

if (isset($_POST["submit"])) {
    // dont validate shit (except already taken names and birthdates)

    $username = trim($_POST['name'] ?? '');
    $email_address = $_POST['email'] ?? ''; //filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $pass = $_POST['password'] ?? ''; //$pass2 = $_POST['pass2'] ?? '';
    $birthdate = $_POST['birthdate'] ?? '';

    // ok now process the shit
    try {
        $token = bin2hex(random_bytes(32));
    } catch (RandomException $e) {
        die("An error occurred while generating your token.");
    }

    if ($database->fetch("SELECT COUNT(*) FROM user_old_names WHERE old_name = ?", [$username])["COUNT(*)"] >= 1) {
        die("Username already taken");
    }

    try {
        $dobDateTime = new DateTime($birthdate);
    } catch (DateMalformedStringException) {
        die("Malformed birthdate");
    } finally {
        $currentDate = new DateTime();

        $age = $currentDate->diff($dobDateTime)->y;

        if ($age < 13) {
            die("Under 13 birthdate");
        }
    }

    $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
    $database->query(
        "INSERT INTO accounts (email, password, token, registered, last_login, ip, birthdate)
                          VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$email_address, $hashedPassword, $token, time(), time(), Utilities::getIpAddress(), $dobDateTime->format('Y-m-d')]
    );

    $accid = $database->insertId();

    $database->query(
        "INSERT INTO users (name, password, token, joined, last_seen, title, email, ip, birthdate)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$username, "", $token, time(), time(), $username, $email_address, Utilities::getIpAddress(), $dobDateTime->format('Y-m-d')]
    );

    $userid = $database->insertId();

    $database->query(
        "INSERT INTO account_user_roles (account, user, role)
                          VALUES (?, ?, ?)",
        [$accid, $userid, 3]
    );

    if ($sb->isDiscordWebhookEnabled()) {
        $data = [
            "username" => $username,
            "email" => $email_address,
            "ip" => Utilities::getIpAddress(),
            "asn" => ($ipInfo['as_name'] ?? "Unknown") . " (" . ($ipInfo['asn'] ?? "Unknown") . ")",
        ];

        $sb->getDiscordWebhookClass()->newUserHook($data);
    }
}
?>
<h1>Create account <span style="color:gold;">NEW!</span></h1>
<p style="color:red">don't use this on qa if you have to make someone's account. they won't be able to log on prod.</p>
<form action="/debug/create_account_new" method="post">
    <fieldset>
        <legend>Basic Information</legend>
        <label for="name">Username:</label>
        <input type="text" id="name" name="name" maxlength="128" required><br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" maxlength="128" required><br><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" maxlength="128" required><br><br>

        <label for="birthdate">Birthdate:</label>
        <input type="date" id="birthdate" name="birthdate">
    </fieldset>

    <input type="submit" name="submit" value="Register">
</form>