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

namespace Actions;

class JournalActions extends BaseActions
{
    public function canWrite(): int|ActionError
    {
        $user = $this->requireVerified();

        if ($user instanceof ActionError) {
            return $user;
        }

        if ($this->sb->isLockdownEnabled()) {
            return ActionError::WriteDisabled;
        }

        if ($this->database->result("SELECT COUNT(*) FROM journals WHERE timestamp > ? AND author = ?", [time() - 60, $user]) && !$this->sb->isDebug()) {
            return ActionError::WriteRatelimit;
        }

        return $user;
    }

    public function create(string $title, ?string $description, bool $news = false): int|ActionError
    {
        $user = $this->canWrite();

        if ($user instanceof ActionError) {
            return $user;
        }

        $isSiteNews = ($this->auth->hasUserAuthenticatedAsStaff() && $news) ? 1 : 0;

        $this->database->query(
            "INSERT INTO journals (title, post, author, timestamp, is_news) VALUES (?,?,?,?,?)",
            [$title, $description, $user, time(), $isSiteNews]
        );

        $journal_id = (int)$this->database->insertId();

        if ($this->sb->isWebhookLoggerEnabled()) {
            $data = [
                'id' => $journal_id,
                'name' => $title,
                'description' => $description,
                'author' => $this->auth->getUserData()["name"],
                'is_news' => $isSiteNews,
            ];

            $this->sb->getWebhookLoggerClass()->newJournalHook($data);
        }

        return $journal_id;
    }
}
