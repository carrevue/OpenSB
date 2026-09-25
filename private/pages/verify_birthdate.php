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

if ($sb->isIpLookupEnabled()) {
    $country = $sb->getIpLookupClass()->getCountry(Utilities::getIpAddress());
    $age_requirement = Utilities::getMinimumAgeFromCountryCode($country);
    $ban_reason = "Failed birthdate verification check / Below " . $age_requirement . " in " . $sb->getIpLookupClass()->getCountry($country);
} else {
    $age_requirement = 13;
    $ban_reason = "Failed birthdate verification check / Below 13";
}

$age_limit = date('Y') - 120;

if ($sb->getLocalOptions()["skin"] != "trinium") {
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

        if ($dobDateTime->format('Y') < $age_limit || $dobDateTime->format('Y') > date('Y')) {
            Utilities::notifyBanner("notify_birthdate_invalid", "/verify_birthdate");
        }

        $age = $currentDate->diff($dobDateTime)->y;

        if ($age < $age_requirement) {
            $database->query(
                "INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
                [$auth->getUserData()["id"], $ban_reason, time()]
            );
        } else {
            Utilities::notifyBanner("notify_birthdate_success", false, "success");
        }
        $database->query("UPDATE users SET birthdate = ? WHERE id = ?", [$dobDateTime->format('Y-m-d'), $auth->getUserData()["id"]]);
        header('Location: /index');
    }
}

echo $twig->render('verify_birthdate.twig');
