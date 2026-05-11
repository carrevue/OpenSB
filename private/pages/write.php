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

global $sb, $twig, $database, $auth;

use Core\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->isBanned() || $auth->getUserFlags(true)["unverified"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($sb->isLockdownEnabled()) {
    Utilities::notifyBanner("notify_write_disabled", "/");
}

if ($database->result("SELECT COUNT(*) FROM journals WHERE timestamp > ? AND author = ?", [time() - 60, $auth->getUserID()]) && !$sb->isDebug()) {
    Utilities::notifyBanner("notify_write_ratelimit", "/");
}

if ((isset($_POST['upload']) || isset($_POST['upload_video'])) && $auth->isUserLoggedIn()) {
    $uploader = $auth->getUserID();

    $title = ($_POST['title'] ?? "No title");
    $description = ($_POST['desc'] ?? null);

    $isSiteNews = ($auth->hasUserAuthenticatedAsStaff() && ($_POST['news'] ?? false)) ? 1 : 0;

    $database->query(
        "INSERT INTO journals (title, post, author, timestamp, is_news) VALUES (?,?,?,?,?)",
        [$title, $description, $uploader, time(), $isSiteNews]
    );

    $journal_id = $database->insertId();

    if ($sb->isDiscordWebhookEnabled()) {
        $data = [
            'id' => $journal_id,
            'name' => $title,
            'description' => $description,
            'author' => $auth->getUserData()["name"],
            'is_news' => $isSiteNews,
        ];

        $sb->getDiscordWebhookClass()->newJournalHook($data);
    }

    Utilities::notifyBanner("notify_write_success", "/user/" . $auth->getUserData()["name"] . '/journal/' . $journal_id, "success");
}

echo $twig->render('write_journal.twig');
