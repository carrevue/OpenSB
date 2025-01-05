<?php

namespace OpenSB;

global $twig, $orange;

use SquareBracket\Utilities;

if (isset($_POST['apply'])) {
    $options = [];
    if (isset($_COOKIE['SBOPTIONS'])) {
        $options = json_decode(base64_decode($_COOKIE['SBOPTIONS']), true);
    }

    $new = explode(",", $_POST["theme"]);

    $options["skin"] = $new[0];
    $options["theme"] = $new[1];
    $options["sounds"] = $_POST['sounds'] ?? false;

    setcookie("SBOPTIONS", base64_encode(json_encode($options)), 2147483647);

    Utilities::notifyBanner("Successfully changed your settings.", "/", "success");
}

echo $twig->render('theme.twig');
