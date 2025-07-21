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

global $auth, $twig, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdministrator()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("Please change your skin to Trinium.", "/theme");
}

echo $twig->render("admin_temporary.twig");
