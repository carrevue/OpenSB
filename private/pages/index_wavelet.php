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

namespace OpenSB\Pages;

use OpenSB\Utilities;
use RuntimeException;

global $twig, $database, $sb;

$options = $sb->getLocalOptions();

$posts = $database->fetchArray($database->query("SELECT j.* FROM journals j ORDER BY j.timestamp DESC LIMIT 12"));

$data = [
    'posts' => Utilities::makeJournalArray($database, $posts),
];

echo $twig->render('index.twig', [
    'data' => $data,
    'slogan' => Utilities::getRandomSlogan() ?? null,
]);
