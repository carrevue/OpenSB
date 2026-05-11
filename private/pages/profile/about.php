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

namespace Pages;

global $auth, $database, $twig, $sb;

use Core\Utilities;
use Data\Upload\UploadQuery;
$options = $sb->getLocalOptions();

// if we're not on finalium, redirect to the normal profile page.
if ($sb->getCurrentSkinName() != "finalium") {
    Utilities::redirect("/user/$username");
}

include_once('_include.php');

echo $twig->render("profile_about.twig", [
    'common' => $common_data,
]);
