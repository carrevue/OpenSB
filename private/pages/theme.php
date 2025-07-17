<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

global $orange, $twig;

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

    if ($orange->isDebug()) {
        $locales[] = [
            'id' => "psuedo",
            'name' => "Psuedolocale",
        ];
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

    $options["locale"] = $_POST['locale'];

    setcookie("SBOPTIONS", base64_encode(json_encode($options)), [
        'expires' => 2147483647,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    Utilities::notifyBanner("Successfully changed your settings.", "/", "success");
}

echo $twig->render('theme.twig', [
    'locales' => $locales,
]);
