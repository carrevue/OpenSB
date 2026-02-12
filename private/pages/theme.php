<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

global $sb, $twig;

use OpenSB\Utilities;

// scan for all locales
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
                'name' => locale_get_display_name($id, $id),
            ];
        }
    }

    if ($sb->isDebug() || $sb->isTestInstance()) {
        $locales[] = [
            'id' => "psuedo",
            'name' => "[DEBUG] Pseudolocalization",
        ];
    }
}

// the code to handle scanning skins/themes is in the templating class

if (isset($_POST['apply'])) {
    $options = [];
    $options = $sb->getOptionsCookie();

    $new = explode(",", $_POST["theme"]);

    $options["skin"] = $new[0];
    $options["theme"] = $new[1];

    $options["locale"] = $_POST['locale'];

    // TEMPORARY: on finalium, include the option to enable SPF
    if ($options["skin"] == "finalium") {
        $options["finalium_enable_spf"] = isset($_POST['spf']) && $_POST['spf'] == "on";
    }

    $sb->setOptionCookie($options);

    Utilities::notifyBanner("notify_successfully_updated_options", "/", "success");
}

echo $twig->render('theme.twig', [
    'locales' => $locales,
]);
