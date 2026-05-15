<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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

use DateMalformedStringException;
use DateTime;

use Core\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if (isset($auth->getUserData()['birthdate']) && !$sb->isDebug()) {
    Utilities::redirect("/");
}

if ($sb->getCurrentSkinName() != "trinium") {
    $options = $sb->getOptionsCookie();

    $options["skin"] = "trinium";
    $options["theme"] = "default";

    $sb->setOptionCookie($options);

    Utilities::redirect("/verify_birthdate");
}

if (isset($_POST['birthdatesubmit'])) {
    $birthdate = $_POST['birthdate'] ?? '';

    try {
        $dobDateTime = new DateTime($birthdate);
    } catch (DateMalformedStringException $e) {
        Utilities::notifyBanner("notify_birthdate_fail", "/verify_birthdate");
    } finally {
        $currentDate = new DateTime();

        if ($dobDateTime->format('Y') < 1900 || $dobDateTime->format('Y') > date('Y')) {
            Utilities::notifyBanner("notify_birthdate_invalid", "/verify_birthdate");
        }

        $age = $currentDate->diff($dobDateTime)->y;

        if ($age < 13) {
            // TROLLED
            $database->query(
                "INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
                [$auth->getUserData()["id"], "Failed birthdate verification check / Below 13", time()]
            );
        } else {
            Utilities::notifyBanner("notify_birthdate_success", false, "success");
        }
        $database->query("UPDATE users SET birthdate = ? WHERE id = ?", [$dobDateTime->format('Y-m-d'), $auth->getUserData()["id"]]);
        header('Location: /index');
    }
}

echo $twig->render('verify_birthdate.twig');
