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

use Core\Utilities;
use Actions\PlaylistActions;
use Actions\ActionError;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$username = fn($id) => htmlspecialchars(Utilities::userIDToUsername($database, (int)$id) ?: "#$id");

$actions = new PlaylistActions($sb);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $playlist = $_POST['playlist'] ?? '';
    $upload = $_POST['item'] ?? '';

    $action = $_POST['action'] ?? '';

    if ($action === 'up' || $action === 'down') {
        $position = $actions->position($playlist, $upload);

        if ($position instanceof ActionError) {
            die($sb->getLocalizationClass()->translate($position->value));
        }
    }

    $result = match ($action) {
        'create' => $actions->create($_POST['title'] ?? '', $_POST['description'] ?? null),
        'delete' => $actions->delete($playlist),
        'add' => $actions->add($playlist, $_POST['upload'] ?? ''),
        'remove' => $actions->remove($playlist, $upload),
        'up' => $actions->move($playlist, $upload, $position - 1),
        'down' => $actions->move($playlist, $upload, $position + 1),
        'move' => $actions->move($playlist, $upload, (int)($_POST['position'] ?? 0)),
        'fix' => $actions->fix($playlist),
        default => null,
    };

    if ($result instanceof ActionError) {
        die($sb->getLocalizationClass()->translate($result->value));
    }

    if ($action === 'create') {
        $playlist = $result;
    }

    if ($action === 'delete') {
        Utilities::redirect('/debug/playlists');
    }

    Utilities::redirect('/debug/playlists?id=' . urlencode($playlist));
}

$filter = trim($_GET['user'] ?? '');

if ($filter !== '') {
    $playlists = $database->fetchArray($database->query(
        "SELECT p.* FROM playlists p JOIN users u ON u.id = p.author WHERE u.name = ? ORDER BY p.timestamp DESC",
        [$filter]
    ));
} else {
    $playlists = $database->fetchArray($database->query("SELECT * FROM playlists ORDER BY timestamp DESC"));
}

$data = isset($_GET['id']) ? $database->fetch("SELECT * FROM playlists WHERE playlist_id = ?", [$_GET['id']]) : null;
?>
<h1>Playlists</h1>
<p>Logged in as <?= $auth->isLoggedIn() ? $username($auth->getUserID()) : 'nobody' ?></p>

<form method="get" action="/debug/playlists">
    <input type="text" name="id" placeholder="playlist id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">
    <input type="submit" value="Load">
</form>

<form method="get" action="/debug/playlists">
    <input type="text" name="user" placeholder="username" value="<?= htmlspecialchars($filter) ?>">
    <input type="submit" value="Filter">
</form>

<?php if ($auth->isLoggedIn()): ?>
    <form method="post">
        <input type="hidden" name="action" value="create">
        <input type="text" name="title" placeholder="title">
        <input type="text" name="description" placeholder="description">
        <input type="submit" value="Create">
    </form>
<?php endif; ?>

<table border="1">
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Author</th>
        <th>Uploads</th>
    </tr>
    <?php foreach ($playlists as $row): ?>
        <tr>
            <td>
                <?php if ($data && $row['playlist_id'] === $data['playlist_id']): ?>
                    <b><?= htmlspecialchars($row['playlist_id']) ?></b>
                <?php else: ?>
                    <a href="/debug/playlists?<?= htmlspecialchars(http_build_query(array_filter(['id' => $row['playlist_id'], 'user' => $filter]))) ?>"><?= htmlspecialchars($row['playlist_id']) ?></a>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= $username($row['author']) ?></td>
            <td><?= (int)$row['upload_count'] ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<?php
if (isset($_GET['id']) && !$data) {
    echo '<p>not found</p>';
    return;
}

if (!$data) {
    return;
}

$owner = $actions->owns($data);

$items = $database->fetchArray($database->query(
    "SELECT i.upload, i.position, v.upload_id, v.title
     FROM playlist_items i LEFT JOIN uploads v ON v.id = i.upload
     WHERE i.playlist = ?
     ORDER BY i.position, i.id",
    [$data['id']]
));

$form = fn(string $action, array $fields = [], string $extra = '') => sprintf(
    '<form method="post" style="display:inline"><input type="hidden" name="playlist" value="%s"><input type="hidden" name="action" value="%s">%s%s<input type="submit" value="%s"></form>',
    htmlspecialchars($data['playlist_id']),
    $action,
    implode('', array_map(fn($k, $v) => sprintf('<input type="hidden" name="%s" value="%s">', $k, htmlspecialchars($v)), array_keys($fields), $fields)),
    $extra,
    ucfirst($action)
);
?>
<h2><?= htmlspecialchars($data['title']) ?></h2>
<p>
    <a href="/collection/<?= htmlspecialchars($data['playlist_id']) ?>"><?= htmlspecialchars($data['playlist_id']) ?></a>
    by <?= $username($data['author']) ?>,
    upload_count <?= (int)$data['upload_count'] ?>
</p>
<?php if ($owner): ?>
    <p>
        <?= $form('fix') ?>
        <?= $form('delete') ?>
    </p>

    <form method="post">
        <input type="hidden" name="playlist" value="<?= htmlspecialchars($data['playlist_id']) ?>">
        <input type="hidden" name="action" value="add">
        <input type="text" name="upload" placeholder="upload id">
        <input type="submit" value="Add">
    </form>
<?php else: ?>
    <p>Not your playlist</p>
<?php endif; ?>

<table border="1">
    <tr>
        <th>Position</th>
        <th>Upload</th>
        <?php if ($owner): ?>
            <th></th>
        <?php endif; ?>
    </tr>
    <?php foreach ($items as $item): ?>
        <tr>
            <td><?= (int)$item['position'] ?></td>
            <td>
                <?php if ($item['upload_id']): ?>
                    <a href="/view/<?= htmlspecialchars($item['upload_id']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                <?php else: ?>
                    missing (#<?= (int)$item['upload'] ?>)
                <?php endif; ?>
            </td>
            <?php if ($owner): ?>
                <td>
                    <?php if ($item['upload_id']): ?>
                        <?= $form('up', ['item' => $item['upload_id']]) ?>
                        <?= $form('down', ['item' => $item['upload_id']]) ?>
                        <?= $form('move', ['item' => $item['upload_id']], '<input type="number" name="position" size="3" min="0">') ?>
                        <?= $form('remove', ['item' => $item['upload_id']]) ?>
                    <?php endif; ?>
                </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
