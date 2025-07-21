<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

global $orange, $twig, $database, $auth;

use SquareBracket\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("Please login to continue.", "/login");
}

if ($auth->getUserBanData()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if ($orange->isLockdownEnabled()) {
    Utilities::notifyBanner("The ability to write journals has been disabled.", "/");
}

if ($database->result("SELECT COUNT(*) FROM journals WHERE date > ? AND author = ?", [time() - 60, $auth->getUserID()]) && !$orange->isDebug()) {
    Utilities::notifyBanner("Please wait a minute before posting another journal.", "/");
}

if (isset($_POST['upload']) or isset($_POST['upload_video']) and $auth->isUserLoggedIn()) {
    $uploader = $auth->getUserID();

    $title = ($_POST['title'] ?? "No title");
    $description = ($_POST['desc'] ?? null);

    $isSiteNews = ($auth->hasUserAuthenticatedAsStaff() && ($_POST['news'] ?? false)) ? 1 : 0;

    $database->query(
        "INSERT INTO journals (title, post, author, date, is_site_news) VALUES (?,?,?,?,?)",
        [$title, $description, $uploader, time(), $isSiteNews]
    );
    
    $journal_id = $database->insertId();

    Utilities::notifyBanner("Your journal has been posted.", "/read/" . $journal_id, "success");
}

echo $twig->render('write_journal.twig');
