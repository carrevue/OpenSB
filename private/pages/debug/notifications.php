<?php

namespace OpenSB;

use SquareBracket\NotificationEnum;
use SquareBracket\Utilities;

global $orange, $database, $auth;
// and nope, no twig here. this is a quick and dirty page meant to test out notifications.
// its fucking ugly and shouldnt be used as a reference point for the opensb codebase

if (!$orange->isDebug()) {
    http_response_code(403);
    die();
}

if (isset($_POST["submit"])) {
    Utilities::notifyUser($database,
        $_POST["user_id"],
        $_POST["location_id"],
        $_POST["related_id"],
        NotificationEnum::from($_POST["notification_type"]));
}

?>
<h1>Notifications</h1>
<div>
<?php
    if ($auth->isUserLoggedIn()) {
        echo "Logged in";
    } else {
        echo "NOT LOGGED IN";
    }
?>
</div>
<div>
    <h2>Create-a-Notification</h2>
    <form action="/debug/notifications" method="post">
        <div>
            <label for="user_id">User ID:</label>
            <input type="number" id="user_id" name="user_id" value="1" required>
        </div>

        <div>
            <label for="location_id">Location ID:</label>
            <input type="number" id="location_id" name="location_id" required>
        </div>

        <div>
            <label for="related_id">Related ID:</label>
            <input type="number" id="related_id" name="related_id" required>
        </div>

        <div>
            <label for="notification_type">Notification Type:</label>
            <select id="notification_type" name="notification_type" required>
                <?php
                foreach (NotificationEnum::cases() as $case) {
                    echo sprintf(
                        '<option value="%s">%s</option>',
                        $case->value,
                        $case->name
                    );
                }
                ?>
            </select>
        </div>

        <div>
            <input type="submit" name="submit" value="Notify User">
        </div>
    </form>
</div>
<div>
    <h2>All notifications</h2>
    <?php
    $data = $database->fetchArray($database->query("SELECT * FROM user_notifications ORDER BY id DESC"));
    ?>
    <table border="1">
        <tr>
            <th>ID</th>
            <th>Type</th>
            <th>Level</th>
            <th>Recipient</th>
            <th>Sender</th>
            <th>Timestamp</th>
            <th>Related ID</th>
        </tr>
        <?php foreach ($data as $n): ?>
            <tr>
                <td><?= $n['id'] ?></td>
                <td><?= NotificationEnum::from($n['type'])->name ?></td>
                <td><?= $n['level'] ?></td>
                <td><?= $n['sender'] ?></td>
                <td><?= $n['recipient'] ?></td>
                <td><?= date('Y-m-d H:i:s', $n['timestamp']) ?></td>
                <td><?= $n['related_id'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</div>
