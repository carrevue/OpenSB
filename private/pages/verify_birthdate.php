<?php

namespace OpenSB;

global $twig, $database, $auth, $orange;

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
    setcookie("SBOPTIONS", base64_encode(json_encode($this->options)), 2147483647);
    header(sprintf('Location: /verify_birthdate'));
    die();
}

if (isset($_POST['birthdatesubmit'])) {
    $birthdate = $_POST['birthdate'] ?? '';

    try {
        $dobDateTime = new DateTime($birthdate);
    } catch (\DateMalformedStringException $e) {
        Utilities::notifyBanner("Failed to process birthdate, please verify again.", "/verify_birthdate");
    }

    $currentDate = new DateTime();

    $age = $currentDate->diff($dobDateTime)->y;

    if ($age < 13) {
        // TROLLED
        $database->query("INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
            [$auth->getUserData()["id"], "Failed birthdate verification check / Below 13", time()]);
    } else {
        Utilities::notifyBanner("You have been successfully verified.", false, "success");
    }
    $database->query("UPDATE users SET birthdate = ? WHERE id = ?", [$dobDateTime->format('Y-m-d'), $auth->getUserData()["id"]]);
    header('Location: /index');
}

echo $twig->render('verify_birthdate.twig');