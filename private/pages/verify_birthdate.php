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

global $twig, $database, $auth, $orange;

use DateMalformedStringException;
use DateTime;
use SquareBracket\Utilities;

if (isset($auth->getUserData()['birthdate']) && !$orange->isDebug()) {
    header('Location: /index');
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    if (isset($_COOKIE['SBOPTIONS'])) {
        $options = json_decode(base64_decode($_COOKIE['SBOPTIONS']), true);
    }

    $options["skin"] = "trinium";

    $options["theme"] = "default";

    setcookie("SBOPTIONS", base64_encode(json_encode($options)), [
        'expires' => 2147483647,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    Utilities::redirect("/verify_birthdate");
}

if (isset($_POST['birthdatesubmit'])) {
    $birthdate = $_POST['birthdate'] ?? '';

    try {
        $dobDateTime = new DateTime($birthdate);
    } catch (DateMalformedStringException $e) {
        Utilities::notifyBanner("Failed to process birthdate, please verify again.", "/verify_birthdate");
    }

    $currentDate = new DateTime();

    $age = $currentDate->diff($dobDateTime)->y;

    if ($age < 13) {
        // TROLLED
        $database->query(
            "INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
            [$auth->getUserData()["id"], "Failed birthdate verification check / Below 13", time()]
        );
    } else {
        Utilities::notifyBanner("You have been successfully verified.", false, "success");
    }
    $database->query("UPDATE users SET birthdate = ? WHERE id = ?", [$dobDateTime->format('Y-m-d'), $auth->getUserData()["id"]]);
    header('Location: /index');
}

echo $twig->render('verify_birthdate.twig');
