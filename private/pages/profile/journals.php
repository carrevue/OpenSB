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

global $twig, $database, $auth, $sb;

use Core\Utilities;
use Data\Journal\JournalQuery;

include_once('_include.php');

// page-specific shit Here.

$journal_count = 0;

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$journal_query = new JournalQuery($sb);

$journals = $journal_query->query("j.timestamp DESC", $limit, "j.author = ?", [$data["id"]])->toCleanArray();
$count = $journal_query->count("j.author = ?", [$data["id"]]);

if ($sb->getCurrentSkinName() == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapSkinProfileGradient($data["userlink_color"]);
}

echo $twig->render('profile_journals.twig', [
    'common' => $common_data,
    'journals' => $journals,
    'page' => $page,
    'count' => $count
]);
