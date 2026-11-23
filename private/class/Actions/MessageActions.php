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

use Data\Message\MessageData;

class MessageActions extends BaseActions
{
    public function send(int $recipient, string $title, string $contents, ?int $replyTo = null): int|ActionError
    {
        $user = $this->requireVerified();

        if ($user instanceof ActionError) {
            return $user;
        }

        if ($recipient == $user) {
            return ActionError::CannotMessageSelf;
        }

        if (!$this->database->result("SELECT COUNT(*) FROM users WHERE id = ?", [$recipient])) {
            return ActionError::InvalidUser;
        }

        $title = trim($title);
        $contents = trim($contents);

        if ($contents === "") {
            return ActionError::MessageEmpty;
        }

        if (strlen($contents) > 10000) {
            return ActionError::MessageTooLong;
        }

        if (mb_strlen($title) > 128) {
            return ActionError::MessageTitleTooLong;
        }

        if ($replyTo) {
            $original = $this->find($replyTo, $user);

            if ($original instanceof ActionError) {
                return $original;
            }

            if ($title === "") {
                $title = mb_substr(str_starts_with($original["title"], "Re: ") ? $original["title"] : "Re: " . $original["title"], 0, 128);
            }
        }

        if (!$this->sb->isDebug() && $this->database->result("SELECT COUNT(*) FROM private_messages WHERE author = ? AND date > ?", [$user, time() - 30])) {
            return ActionError::MessageRatelimit;
        }

        $this->database->query(
            "INSERT INTO private_messages (reply_to_id, title, contents, author, recipient, date) VALUES (?,?,?,?,?,?)",
            [$replyTo ?: null, $title, $contents, $user, $recipient, time()]
        );

        $id = (int)$this->database->insertId();

        $this->auth->bumpLastActive();

        return $id;
    }

    public function open(int $id): array|ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $message = $this->find($id, $user);

        if ($message instanceof ActionError) {
            return $message;
        }

        if ($message["recipient"] == $user && $message["read_date"] === null) {
            $this->database->query("UPDATE private_messages SET read_date = ? WHERE id = ?", [time(), $id]);
        }

        return (new MessageData($this->database))->getMessage($id);
    }

    public function delete(int $id): ?ActionError
    {
        $user = $this->requireLogin();

        if ($user instanceof ActionError) {
            return $user;
        }

        $message = $this->find($id, $user);

        if ($message instanceof ActionError) {
            return $message;
        }

        $column = $message["author"] == $user ? "deleted_by_author" : "deleted_by_recipient";

        $this->database->query("UPDATE private_messages SET {$column} = 1 WHERE id = ?", [$id]);
        $this->database->query("DELETE FROM private_messages WHERE id = ? AND deleted_by_author = 1 AND deleted_by_recipient = 1", [$id]);

        return null;
    }

    private function find(int $id, int $user): array|ActionError
    {
        $message = $this->database->fetch("SELECT * FROM private_messages WHERE id = ?", [$id]);

        if (!$message) {
            return ActionError::InvalidMessage;
        }

        if ($message["author"] == $user && !$message["deleted_by_author"]) {
            return $message;
        }

        if ($message["recipient"] == $user && !$message["deleted_by_recipient"]) {
            return $message;
        }

        return ActionError::InvalidMessage;
    }
}
