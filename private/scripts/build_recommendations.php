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

namespace OpenSB;

define("SB_ROOT_PATH", dirname(__DIR__, 2));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once SB_PRIVATE_PATH . '/common.php';

use Data\Recommendation\RecommendationBuilder;

global $database, $sb;

ini_set('memory_limit', '1024M');

$viewsSince = $sb->isChazizInstance() ? 1753070400 : 0; // sb specific behavior (pre-july 21st 2025 views don't have ips)
$ignoredWords = $sb->isChazizInstance() ? ['fulptube', 'squarebracket', 'poktube', 'qobo', 'gamerappa', 'chaziz'] : []; // sb specific behavior (ignore meta shit)

$builder = new RecommendationBuilder(
    $database,
    $sb->isIpLookupEnabled() ? $sb->getIpLookupClass() : null,
    fn($message) => print(date('c') . " $message\n"),
    $viewsSince,
    $ignoredWords
);
$stats = $builder->build(true, in_array('--force', $argv, true));

echo date('c') . " done: " . json_encode($stats) . "\n";