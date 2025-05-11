<?php

namespace OpenSB;

global $auth, $twig, $database, $orange, $path;

use SquareBracket\UserData;
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

$username = $path[3] ?? null;

$user = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$user)
{
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /admin/users/$new_username");
        exit();
    } else {
        Utilities::notifyBanner("This user does not exist.", "/admin/");
    }
}

if (isset($_POST['ban_user'])) {
    // Don't ban non-existent users.
    if (!$database->fetch("SELECT u.name FROM users u WHERE u.name = ?", [$_POST["ban_user"]])) {
        Utilities::notifyBanner("This user does not exist.", "/admin/users/");
    }
    // Don't ban mods/admins.
    if ($database->fetch("SELECT u.powerlevel FROM users u WHERE u.name = ?", [$_POST["ban_user"]])["powerlevel"] != 1) {
        Utilities::notifyBanner("This user cannot be banned.", "/admin/users/");
    }
    // Check if user is already banned, if not, then ban. Otherwise, unban.
    $id = $database->fetch("SELECT u.id FROM users u WHERE u.name = ?", [$_POST["ban_user"]])["id"];
    if ($database->fetch("SELECT b.userid FROM user_bans b WHERE b.userid = ?", [$id])) {
        $database->query("DELETE FROM user_bans WHERE userid = ?", [$id]);
        Utilities::notifyBanner("Unbanned " . $_POST["ban_user"] . '.' , "/admin/users", "success");
    } else {
        $database->query("INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
            [$id, "Banned by " . $auth->getUserData()["name"], time()]);
        Utilities::notifyBanner("Banned " . $_POST["ban_user"] . '.', "/admin/users", "success");
    }
}

if ($user["ip"] != "999.999.999.999") {
    $users_with_matching_ips = $database->fetchArray($database->query("SELECT u.name, u.title FROM users u WHERE u.ip = ? AND id != ?",
        [$user["ip"], $user["id"]]));
} else {
    $users_with_matching_ips = [];
}

$old_username_data = $database->fetchArray($database->query("SELECT * FROM user_old_names WHERE user = ?", [$user["id"]]));

$notes = $database->fetchArray($database->query("SELECT * FROM user_staff_notes WHERE user = ?", [$user["id"]]));

$notes_proper = [];

foreach ($notes as $note) {
    $userData = new UserData($database, $note["author"]);
    $notes_proper[] = [
        "content" => $note["note"],
        "time" => $note["time"],
        "author" => [
            "id" => $note["author"],
            "info" => $userData->getUserArray(),
        ],
    ];
}

echo $twig->render('admin_user_edit.twig', [
    'user' => $user,
    'users_with_matching_ips' => $users_with_matching_ips,
    'notes' => $notes_proper,
    'old_names' => $old_username_data
]);