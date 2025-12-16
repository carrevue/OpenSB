<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

global $auth, $twig, $database, $sb, $path;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;
use OpenSB\Composer\ComposerInstalled;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_frontend_switch_required", "/theme", "primary", ["Trinium"]);
}

function getComposerPackages(): array
{
    $dependencies = [];
    $installed = new ComposerInstalled(BLUFF_VENDOR_PATH . '/composer/installed.json');
    $dependencies += $installed->getInstalledDependencies();
    ksort($dependencies);
    return $dependencies;
}

$data = [
    "packages" => getComposerPackages(),
];

echo $twig->render("dashboard_server.twig", [
    'data' => $data
]);
