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

namespace OpenSB\Pages;

global $sb, $twig, $twig_error, $database, $auth;

use OpenSB\UserData;
use OpenSB\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->isBanned()) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($auth->getUserFlags(true)["unverified"]) {
    http_response_code(403);
    echo $twig->render('unverified.twig');
    die();
}

if (!$sb->isInviteKeysEnabled()) {
    http_response_code(404);
    echo $twig_error->render("404.twig", ["page" => "failwhale"]);
    die();
}

$cooldown = false;
$latest_invite_key = $database->result("SELECT generated_time FROM invite_keys WHERE generated_by = ? ORDER BY generated_time DESC LIMIT 1", [$auth->getUserID()]);

if (
    $auth->getUserData()["joined"] >= (time() - 604800)
|| $latest_invite_key >= (time() - 86400)
) {
    $cooldown = true;
}

if (isset($_POST["action"])) {
    if ($_POST["action"] == "generate_invite_key") {
        if ($cooldown) {
            Utilities::notifyBanner("notify_no_permission", "/my_invite_keys");
        }

        $random = strtoupper("SBU_" . Utilities::generateRandomString(32));

        $database->query(
            "INSERT INTO invite_keys (invite_key, generated_by, generated_time) VALUES (?,?,?)",
            [$random, $auth->getUserID(), time()]
        );

        Utilities::notifyBanner("notify_dashboard_key_generated", "/my_invite_keys", "success", [$random]);
    }
}

// Get the invite keys
$inviteKeys = $database->fetchArray($database->query("SELECT * FROM invite_keys WHERE generated_by = ?", [$auth->getUserId()]));

$inviteKeyData = [];
foreach ($inviteKeys as $inviteKey) {
    //$generatedBy = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$inviteKey["generated_by"]]);
    //$claimedBy = $database->fetch("SELECT u.name FROM users u WHERE u.id = ?", [$inviteKey["claimed_by"]]);

    //$inviteKey["generated_by"] = $generatedBy;
    //$inviteKey["claimed_by"] = $claimedBy;

    if ($inviteKey["claimed_by"]) {
        $claimee_data = new UserData($database, $inviteKey["claimed_by"]);
        $inviteKey["claimee"]["id"] = $inviteKey["claimed_by"]; // ??????
        $inviteKey["claimee"]["info"] = $claimee_data->getUserArray();
    }

    $inviteKeyData[] = $inviteKey;
}

$data = [
    "invites" => $inviteKeyData,
];

echo $twig->render("my_invite_keys.twig", [
    'data' => $data,
    'cooldown' => $cooldown,
]);
