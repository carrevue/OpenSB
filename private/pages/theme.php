<?php

namespace OpenSB;

global $twig;

use SquareBracket\Utilities;

// scan for localizations
$localesPath = SB_PRIVATE_PATH . "/locales/";
$locales = [];

if (is_dir($localesPath)) {
    $files = scandir($localesPath);

    foreach ($files as $file) {
        $filePath = $localesPath . $file;

        if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
            $id = pathinfo($filePath, PATHINFO_FILENAME);
            $locales[] = [
                'id' => $id,
                'name' => $id, // TODO
            ];
        }
    }
}

// the code to handle scanning themes is in the templating class

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

echo $twig->render('theme.twig', [
    'locales' => $locales,
]);
