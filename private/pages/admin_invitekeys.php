<?php

namespace OpenSB;

global $auth, $twig, $database, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::notifyBanner("Please change your skin to Biscuit.", "/theme");
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
        $random = strtoupper("SB" . Utilities::generateRandomString(32));

        $database->query("INSERT INTO invite_keys (invite_key, generated_by, generated_time) VALUES (?,?,?)",
            [$random, $auth->getUserID(), time()]);

        Utilities::notifyBanner("Generated key! ($random)", "/admin/invitekeys", "success");
    }
}

$data = [
    "invites" => $inviteKeyData,
];

echo $twig->render("admin_invite_keys.twig", [
    'data' => $data
]);