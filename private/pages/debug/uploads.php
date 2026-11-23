<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace Pages\Debug;

global $sb, $database, $auth;

use Actions\ActionError;
use Actions\UploadActions;
use Core\Utilities;
use Data\Upload\UploadData;
use Data\Upload\UploadFlags;
use Data\Upload\UploadVisibilityEnum;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$username = fn($id) => htmlspecialchars(Utilities::userIDToUsername($database, (int)$id) ?: "#$id");

$actions = new UploadActions($sb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['upload'] ?? '';
    $action = $_POST['action'] ?? '';

    $flags = 0;
    foreach (UploadFlags::cases() as $flag) {
        if (isset($_POST['flags'][$flag->name])) {
            $flags |= $flag->value;
        }
    }

    $result = match ($action) {
        'edit' => $actions->edit(
            $id,
            $_POST['title'] ?? null,
            $_POST['description'] ?? null,
            UploadVisibilityEnum::tryFrom((int)($_POST['visibility'] ?? 0)) ?? UploadVisibilityEnum::Public,
            isset($_POST['block_guests']),
            isset($_POST['block_comments']),
            !empty($_FILES['thumbnail']['tmp_name']) ? $_FILES['thumbnail']['tmp_name'] : null
        ),
        'delete' => $actions->delete($id),
        'rate' => $actions->rate($id, (int)($_POST['rating'] ?? 0)),
        'unrate' => $actions->unrate($id),
        'takedown' => $actions->takedown($id, $_POST['reason'] ?? null),
        'restore' => $actions->restore($id),
        'flags' => $actions->setFlags($id, $flags),
        default => null,
    };

    if ($result instanceof ActionError) {
        die($sb->getLocalizationClass()->translate($result->value));
    }

    Utilities::redirect($action === 'delete' ? '/debug/uploads' : '/debug/uploads?id=' . urlencode($id));
}

$filter = trim($_GET['user'] ?? '');

if ($filter !== '') {
    $uploads = $database->fetchArray($database->query(
        "SELECT v.upload_id, v.title, v.author, v.visibility, v.flags FROM uploads v JOIN users u ON u.id = v.author WHERE u.name = ? ORDER BY v.id DESC LIMIT 100",
        [$filter]
    ));
} else {
    $uploads = $database->fetchArray($database->query("SELECT upload_id, title, author, visibility, flags FROM uploads ORDER BY id DESC LIMIT 100"));
}

$upload = null;

if (isset($_GET['id'])) {
    $upload = new UploadData($database, $_GET['id']);
    $data = $upload->getData();

    if (!$data || !$data['id']) {
        $upload = null;
    }
}

$form = fn(string $action, string $fields = '', string $label = '') => sprintf(
    '<form method="post" style="display:inline"><input type="hidden" name="upload" value="%s"><input type="hidden" name="action" value="%s">%s<input type="submit" value="%s"></form>',
    htmlspecialchars($data['upload_id']),
    $action,
    $fields,
    $label ?: ucfirst($action)
);
?>
<h1>Uploads</h1>
<p>Logged in as <?= $auth->isLoggedIn() ? $username($auth->getUserID()) : 'nobody' ?></p>

<form method="get" action="/debug/uploads">
    <input type="text" name="id" placeholder="upload id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
    <input type="submit" value="Load">
</form>

<form method="get" action="/debug/uploads">
    <input type="text" name="user" placeholder="username" value="<?= htmlspecialchars($filter) ?>">
    <input type="submit" value="Filter">
</form>

<?php if (isset($_GET['id']) && !$upload): ?>
    <p>not found</p>
<?php elseif ($upload): ?>
    <?php
    $flagArray = $upload->getFlagArray();
    $ratings = $upload->getRatingData();
    $takedown = $upload->isTakenDown() ? ($upload->getTakedownData()[0] ?? []) : null;
    $myRating = $auth->isLoggedIn() ? $database->result("SELECT rating FROM upload_ratings WHERE upload = ? AND user = ?", [$data['id'], $auth->getUserID()]) : null;
    ?>
    <h2><a href="/view/<?= htmlspecialchars($data['upload_id']) ?>"><?= htmlspecialchars($data['title']) ?></a></h2>
    <table border="1">
        <tr><td>id</td><td><?= htmlspecialchars($data['upload_id']) ?> (<?= (int)$data['id'] ?>)</td></tr>
        <tr><td>author</td><td><?= $username($data['author']) ?></td></tr>
        <tr><td>visibility</td><td><?= UploadVisibilityEnum::tryFrom((int)$data['visibility'])?->name ?? (int)$data['visibility'] ?></td></tr>
        <tr><td>flags</td><td><?= htmlspecialchars(implode(' ', array_keys(array_filter($flagArray)))) ?></td></tr>
        <tr><td>rating</td><td><?= round($ratings['average'] ?? 0, 2) ?> (<?= (int)($ratings['total'] ?? 0) ?>)</td></tr>
        <tr><td>your rating</td><td><?= $myRating ? (int)$myRating : '' ?></td></tr>
        <tr><td>taken down</td><td><?= $takedown !== null ? 'yes: ' . htmlspecialchars($takedown['reason'] ?? '') . ' by ' . $username($takedown['sender'] ?? 0) : '' ?></td></tr>
    </table>

    <h3>Edit</h3>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="upload" value="<?= htmlspecialchars($data['upload_id']) ?>">
        <input type="hidden" name="action" value="edit">
        <input type="text" name="title" value="<?= htmlspecialchars($data['title']) ?>">
        <br>
        <textarea name="description" rows="4" cols="60"><?= htmlspecialchars($data['description'] ?? '') ?></textarea>
        <br>
        <select name="visibility">
            <?php foreach (UploadVisibilityEnum::cases() as $visibility): ?>
                <option value="<?= $visibility->value ?>" <?= (int)$data['visibility'] === $visibility->value ? 'selected' : '' ?>><?= $visibility->name ?></option>
            <?php endforeach; ?>
        </select>
        <label><input type="checkbox" name="block_guests" <?= $flagArray['block_guests'] ? 'checked' : '' ?>> block guests</label>
        <label><input type="checkbox" name="block_comments" <?= $flagArray['block_comments'] ? 'checked' : '' ?>> block comments</label>
        <input type="file" name="thumbnail">
        <input type="submit" value="Save">
    </form>
    <p><?= $form('delete') ?></p>

    <h3>Rating</h3>
    <p>
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <?= $form('rate', '<input type="hidden" name="rating" value="' . $i . '">', (string)$i) ?>
        <?php endfor; ?>
        <?= $form('unrate') ?>
    </p>

    <h3>Moderation</h3>
    <p>
        <?= $form('takedown', '<input type="text" name="reason" placeholder="reason">') ?>
        <?= $form('restore') ?>
    </p>
    <form method="post">
        <input type="hidden" name="upload" value="<?= htmlspecialchars($data['upload_id']) ?>">
        <input type="hidden" name="action" value="flags">
        <?php foreach (UploadFlags::cases() as $flag): ?>
            <label><input type="checkbox" name="flags[<?= $flag->name ?>]" <?= (int)$data['flags'] & $flag->value ? 'checked' : '' ?>> <?= $flag->name ?></label>
        <?php endforeach; ?>
        <input type="submit" value="Set flags">
    </form>
<?php endif; ?>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Author</th>
        <th>Visibility</th>
        <th>Flags</th>
    </tr>
    <?php foreach ($uploads as $row): ?>
        <tr>
            <td><a href="/debug/uploads?<?= htmlspecialchars(http_build_query(array_filter(['id' => $row['upload_id'], 'user' => $filter]))) ?>"><?= htmlspecialchars($row['upload_id']) ?></a></td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= $username($row['author']) ?></td>
            <td><?= UploadVisibilityEnum::tryFrom((int)$row['visibility'])?->name ?? (int)$row['visibility'] ?></td>
            <td><?= htmlspecialchars(implode(' ', array_keys(array_filter(UploadFlags::toArray((int)$row['flags']))))) ?></td>
        </tr>
    <?php endforeach; ?>
</table>