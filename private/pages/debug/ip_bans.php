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

use Core\IPBans;
use Core\Utilities;

if (!$sb->isDebug()) {
    http_response_code(403);
    die();
}

$bans = new IPBans($database, $sb->isIpLookupEnabled() ? $sb->getIpLookupClass() : null);
$ip = trim($_GET['ip'] ?? (string)Utilities::getIpAddress());
$ban = $ip ? $bans->find($ip) : null;

// ranges and patterns aren't ips, so look for the ban itself
foreach ($ban ? [] : $bans->rows() as $row) {
    if (strcasecmp(trim($row['ip']), $ip) === 0) {
        $ban = $row;
    }
}
?>
<h1>IP Bans</h1>

<form action="/debug/ip_bans" method="get">
    <input type="text" name="ip" value="<?= htmlspecialchars($ip) ?>">
    <input type="submit" value="Check">
</form>

<?php if (!$ban): ?>
    <p><?= htmlspecialchars($ip) ?> isn't banned.</p>
<?php elseif ($ban['type'] === 'asn'): ?>
    <p><?= htmlspecialchars($ip) ?> can't register, <?= htmlspecialchars($ban['ip']) ?> is banned.</p>
<?php elseif ($ban['type'] === 'ip' || strcasecmp(trim($ban['ip']), $ip) === 0): ?>
    <p><?= htmlspecialchars($ip) ?> is banned: <?= htmlspecialchars($ban['reason']) ?></p>
<?php else: ?>
    <p><?= htmlspecialchars($ip) ?> is in banned <?= $ban['type'] ?> <?= htmlspecialchars($ban['ip']) ?>: <?= htmlspecialchars($ban['reason']) ?></p>
<?php endif; ?>

<table border="1">
    <tr>
        <th>Ban</th>
        <th>Type</th>
        <th>Reason</th>
        <th>Date</th>
    </tr>
    <?php foreach (array_reverse($bans->rows()) as $row): ?>
        <tr>
            <td><?= htmlspecialchars($row['ip']) ?></td>
            <td><?= $row['type'] ?></td>
            <td><?= htmlspecialchars($row['reason']) ?></td>
            <td><?= date('Y-m-d H:i', $row['timestamp']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>