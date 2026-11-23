<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

global $sb, $twig, $auth;

use Core\Utilities;
use Actions\JournalActions;
use Actions\ActionError;

$actions = new JournalActions($sb);

$error = $actions->canWrite();

if ($error instanceof ActionError) {
    Utilities::notifyBanner($error->value, $error == ActionError::LoginRequired ? "/login" : "/");
}

if (isset($_POST['upload']) || isset($_POST['upload_video'])) {
    $journal_id = $actions->create(
        $_POST['title'] ?? "No title",
        $_POST['desc'] ?? null,
        (bool)($_POST['news'] ?? false)
    );

    if ($journal_id instanceof ActionError) {
        Utilities::notifyBanner($journal_id->value, "/");
    }

    Utilities::notifyBanner("notify_write_success", "/user/" . $auth->getUserData()["name"] . '/journal/' . $journal_id, "success");
}

echo $twig->render('write_journal.twig');
