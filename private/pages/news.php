<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2026 Chaziz

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

namespace Pages;

global $twig, $database;

use Core\Utilities;

$journal_count = 0;
$data = [];

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$journal_array = $database->fetchArray($database->query(
    "SELECT j.* FROM journals j WHERE j.is_news = 1 ORDER BY j.timestamp DESC $limit"
));

$journal_count = $database->result(
    "SELECT COUNT(*) FROM journals j WHERE j.is_news = 1"
);

$data = Utilities::makeJournalArray($database, $journal_array);

echo $twig->render('news.twig', [
    'data' => $data,
    'page' => $page,
    'count' => $journal_count
]);
