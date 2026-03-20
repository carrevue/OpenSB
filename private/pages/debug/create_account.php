<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace OpenSB\Pages\Debug;

global $sb, $database;

use DateMalformedStringException;
use DateTime;
use Random\RandomException;
use OpenSB\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

if (isset($_POST["submit"])) {
    // dont validate shit (except already taken names and birthdates)

    $username = trim($_POST['name'] ?? '');
    $email_address = $_POST['email'] ?? ''; //filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $pass = $_POST['password'] ?? ''; //$pass2 = $_POST['pass2'] ?? '';
    $title = trim($_POST['title']) === '' ? $username : $_POST['title'];
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
        "INSERT INTO users (name, password, token, joined, last_seen, title, email, ip, birthdate)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$username, $hashedPassword, $token, time(), time(), $username, $email_address, "ip", $dobDateTime->format('Y-m-d')]
    );

    if ($sb->isDiscordWebhookEnabled()) {
        $data = [
            "username" => $username,
            "email" => $email_address,
            "ip" => Utilities::getIpAddress(),
            "asn" => $ipInfo['asn'] ?? "Unknown",
        ];

        $sb->getDiscordWebhookClass()->newUserHook($data);
    }
}
?>
<h1>Create account</h1>
<form action="/debug/create_account" method="post">
    <fieldset>
        <legend>Basic Information</legend>
        <label for="name">Username:</label>
        <input type="text" id="name" name="name" maxlength="128" required><br><br>

        <label for="email">Email:</label>
        <input type="email" id="email" name="email" maxlength="128" required><br><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" maxlength="128" required><br><br>

        <label for="title">Display Name:</label>
        <input type="text" id="title" name="title"><br><br>

        <label for="birthdate">Birthdate:</label>
        <input type="date" id="birthdate" name="birthdate">
    </fieldset>

    <input type="submit" name="submit" value="Register">
</form>