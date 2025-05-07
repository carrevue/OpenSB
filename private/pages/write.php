<?php

namespace OpenSB;

global $twig, $database, $disableWritingJournals, $auth, $isDebug;

use SquareBracket\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserBanData()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if ($disableWritingJournals) {
    Utilities::notifyBanner("The ability to write journals has been disabled.", "/");
}

if ($database->result("SELECT COUNT(*) FROM journals WHERE date > ? AND author = ?", [time() - 60, $auth->getUserID()]) && !$isDebug) {
    Utilities::notifyBanner("Please wait a minute before posting a journal again.", "/");
}

if (isset($_POST['upload']) or isset($_POST['upload_video']) and $auth->isUserLoggedIn()) {
    $uploader = $auth->getUserID();

    $title = ($_POST['title'] ?? "No title");
    $description = ($_POST['desc'] ?? null);

    $isSiteNews = ($auth->hasUserAuthenticatedAsAnAdmin() && ($_POST['news'] ?? false)) ? 1 : 0;

    $database->query("INSERT INTO journals (title, post, author, date, is_site_news) VALUES (?,?,?,?,?)",
        [$title, $description, $uploader, time(), $isSiteNews]);

    // TODO: this should redirect to the journal itself, not the user's profile.
    Utilities::notifyBanner("Your journal has been posted.", "/user/" . $auth->getUserData()["name"], "success");
}

echo $twig->render('write_journal.twig');