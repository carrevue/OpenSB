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

global $sb, $database;

use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

if (!$database->result("SHOW TABLES LIKE 'recommendation_bubbles'")) {
    die("no bubbles yet, run private/scripts/build_recommendations.php");
}

$bubbles = [];
foreach ($database->fetchArray($database->query("SELECT * FROM recommendation_bubbles ORDER BY topic IS NULL, topic, size DESC")) as $row) {
    $row['words'] = json_decode($row['words'], true);
    $row['authors'] = json_decode($row['authors'], true);
    $row['neighbors'] = json_decode($row['neighbors'], true);
    $bubbles[(int)$row['bubble']] = $row;
}

$username = fn(int $id) => htmlspecialchars(Utilities::userIDToUsername($database, $id) ?: "#$id");
$bubbleLink = fn($bubble) => sprintf('<a href="/debug/recommendation_bubbles?bubble=%d">%d</a>', $bubble, $bubble);

$bubble = $_GET['bubble'] ?? null;

if ($bubble !== null && isset($bubbles[(int)$bubble])) {
    $info = $bubbles[(int)$bubble];
    $uploads = $database->fetchArray($database->query(
        "SELECT v.upload_id, v.title, v.author, v.views
         FROM upload_bubbles b JOIN uploads v ON v.id = b.upload
         WHERE b.bubble = ?
         ORDER BY v.views DESC",
        [(int)$bubble]
    ));
?>
    <p><a href="/debug/recommendation_bubbles">Back</a></p>
    <h1>Bubble <?= (int)$bubble ?></h1>
    <p>Words: <?= htmlspecialchars(implode(' ', $info['words'])) ?></p>
    <p>Similar bubbles: <?= implode(' ', array_map($bubbleLink, $info['neighbors'])) ?></p>
    <table border="1">
        <tr>
            <th>Title</th>
            <th>Uploader</th>
            <th>Views</th>
        </tr>
        <?php foreach ($uploads as $upload): ?>
            <tr>
                <td><a href="/view/<?= htmlspecialchars($upload['upload_id']) ?>"><?= htmlspecialchars($upload['title']) ?></a></td>
                <td><?= $username((int)$upload['author']) ?></td>
                <td><?= (int)$upload['views'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php
    return;
}

$examples = [];
foreach ($database->fetchArray($database->query(
    "SELECT b.bubble, v.title FROM upload_bubbles b JOIN uploads v ON v.id = b.upload ORDER BY v.views DESC"
)) as $row) {
    if (count($examples[$row['bubble']] ?? []) < 3) {
        $examples[$row['bubble']][] = $row['title'];
    }
}
?>
<h1>Recommendation Bubbles</h1>
<p>Last built <?= $bubbles ? date('Y-m-d H:i', (int)reset($bubbles)['built']) : 'never' ?>.</p>
<table border="1">
    <tr>
        <th>Topic</th>
        <th>Bubble</th>
        <th>Uploads</th>
        <th>Words</th>
        <th>Uploaders</th>
        <th>Most viewed</th>
        <th>Similar bubbles</th>
    </tr>
    <?php foreach ($bubbles as $id => $info): ?>
        <tr>
            <td><?= $info['topic'] ?? '-' ?></td>
            <td><?= $bubbleLink($id) ?></td>
            <td><?= (int)$info['size'] ?></td>
            <td><?= htmlspecialchars(implode(' ', $info['words'])) ?></td>
            <td>
                <?php foreach ($info['authors'] as $author => $count): ?>
                    <?= $username((int)$author) ?> (<?= (int)$count ?>)<br>
                <?php endforeach; ?>
            </td>
            <td><?= implode('<br>', array_map('htmlspecialchars', $examples[$id] ?? [])) ?></td>
            <td><?= implode(' ', array_map($bubbleLink, $info['neighbors'])) ?></td>
        </tr>
    <?php endforeach; ?>
</table>