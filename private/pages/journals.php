<?php

namespace OpenSB;

global $twig, $database, $auth, $orange;

use SquareBracket\Utilities;

$user = $path[2] ?? null;

$journal_count = 0;
$data = [];

$page_number = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? $_GET['page'] : 1);
$limit = sprintf("%s,%s", (($page_number - 1) * 20), 20);

if ($user) {
    if ($user == "news") {
        $journal_array = $database->fetchArray($database->query(
            "SELECT j.* FROM journals j WHERE j.is_site_news = 1 ORDER BY j.date DESC LIMIT $limit"));

        $journal_count = $database->result(
            "SELECT COUNT(*) FROM journals j WHERE j.is_site_news = 1");
    } else {
        // TODO: handle old names
        $id = Utilities::usernameToID($database, $user);
        if (!$id) {
            Utilities::notifyBanner("This user does not exist.", "/");
        }
        $journal_array = $database->fetchArray($database->query(
            "SELECT j.* FROM journals j WHERE j.author = ? ORDER BY j.date DESC LIMIT $limit", [$id]));

        $journal_count = $database->result(
            "SELECT COUNT(*) FROM journals j WHERE j.author = ?", [$id]);
    }

    $data = Utilities::makeJournalArray($database, $journal_array);
} else {
    Utilities::notifyBanner("This user does not exist.", "/");
}

echo $twig->render('journals.twig', [
    'user' => $user,
    'data' => $data,
    'page' => $page_number,
    'count' => $journal_count
]);
