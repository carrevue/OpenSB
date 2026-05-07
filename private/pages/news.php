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

global $sb, $twig, $database;

use Data\Journal\JournalQuery;

$journal_count = 0;
$data = [];

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$journal_query = new JournalQuery($sb);

$journals = $journal_query->query("j.timestamp DESC", $limit, "j.is_news = 1")->toCleanArray();
$count = $journal_query->count("j.is_news = 1");

echo $twig->render('news.twig', [
    'data' => $journals,
    'page' => $page,
    'count' => $count,
]);
