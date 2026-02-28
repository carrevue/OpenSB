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

namespace OpenSB\Pages;

global $auth, $twig, $database, $sb;

use OpenSB\Utilities;
use OpenSB\UserRoleEnum;

if (!$auth->userHasRole(UserRoleEnum::Administrator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getLocalOptions()["skin"] != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}

// Get the invite keys
$inviteKeys = $database->fetchArray($database->query("SELECT * FROM invite_keys"));

$inviteKeyData = [];
foreach ($inviteKeys as $inviteKey) {
    $generatedBy = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$inviteKey["generated_by"]]);
    $claimedBy = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$inviteKey["claimed_by"]]);

    $inviteKey["generated_by"] = $generatedBy;
    $inviteKey["claimed_by"] = $claimedBy;

    $inviteKeyData[] = $inviteKey;
}

// Admin actions
if (isset($_POST["action"])) {
    if ($_POST["action"] == "generate_invite_key") {
        $random = strtoupper("SBA_" . Utilities::generateRandomString(32));

        $database->query(
            "INSERT INTO invite_keys (invite_key, generated_by, generated_time) VALUES (?,?,?)",
            [$random, $auth->getUserID(), time()]
        );

        Utilities::notifyBanner("notify_invite_key_generated", "/dashboard/invite_keys", "success", [$random]);
    }
}

$data = [
    "invites" => $inviteKeyData,
];

echo $twig->render("dashboard_invite_keys.twig", [
    'data' => $data
]);
