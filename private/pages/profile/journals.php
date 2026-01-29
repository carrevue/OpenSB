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

namespace OpenSB\Pages;

global $twig, $database, $auth, $sb;

use OpenSB\Utilities;

include_once('_include.php');

// page-specific shit Here.

$journal_count = 0;

$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = $database->paginate($page, 20);

$journal_array = $database->fetchArray($database->query(
    "SELECT j.* FROM journals j WHERE j.author = ? ORDER BY j.timestamp DESC $limit",
    [$data["id"]]
));

$journal_count = $database->result(
    "SELECT COUNT(*) FROM journals j WHERE j.author = ?",
    [$data["id"]]
);

// this part is fucking ugly.
$journal_data = Utilities::makeJournalArray($database, $journal_array);

$page_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["userlink_color"],
    "about" => ($data["about"] ?? null),
    "customization" => $profile_customization_data?->getData() ?? false,
];

if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapFrontendProfileGradient($data["userlink_color"]);
}

echo $twig->render('profile_journals.twig', [
    'data' => $page_data,
    'journals' => $journal_data,
    'page' => $page,
    'count' => $journal_count
]);
