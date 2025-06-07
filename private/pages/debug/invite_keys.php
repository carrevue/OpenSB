<?php

namespace OpenSB;
global $orange, $database, $auth;

use SquareBracket\Utilities;

if (!$orange->isDebug()) {
    http_response_code(403);
    die();
}

if (!$auth->isUserLoggedIn()) {
    die("NOT LOGGED IN");
}

if (isset($_POST["submit"])) {
    $random = strtoupper("SBU_" . Utilities::generateRandomString(32));

    $database->query("INSERT INTO invite_keys (invite_key, generated_by, generated_time) VALUES (?,?,?)",
        [$random, $auth->getUserID(), time()]);
}

$data = $database->fetchArray($database->query("SELECT * FROM invite_keys WHERE generated_by = ?", [$auth->getUserId()]));
?>
    <h1>My Invite Keys</h1>
    <p>This is the (prototype) implementation of managing invite keys meant for normal users.
        Staff can manage invite keys through the admin panel.</p>
    <div>
        <form action="/debug/invite_keys" method="post">
            <div>
                <input type="submit" name="submit" value="Generate new key">
            </div>
        </form>
    </div>
<table border="1">
    <tr>
        <th>ID</th>
        <th>Invite Key</th>
        <th>Claimed</th>
        <th>Generated</th>
    </tr>
    <?php foreach ($data as $n): ?>
        <tr>
            <td><?= $n['id'] ?></td>
            <td><?= $n['invite_key'] ?></td>
            <td><?= Utilities::userIDToUsername($database, $n['claimed_by']) ?></td>
            <td><?= date('Y-m-d H:i:s', $n['generated_time']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>