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

namespace Data\Message;

use Core\Database;
use Data\User\UserData;

class MessageData
{
    private Database $database;

    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    public function getInbox(int $user): array
    {
        return array_map([$this, 'format'], $this->database->fetchArray($this->database->query(
            "SELECT * FROM private_messages WHERE recipient = ? AND deleted_by_recipient = 0 ORDER BY date DESC, id DESC",
            [$user]
        )));
    }

    public function getSent(int $user): array
    {
        return array_map([$this, 'format'], $this->database->fetchArray($this->database->query(
            "SELECT * FROM private_messages WHERE author = ? AND deleted_by_author = 0 ORDER BY date DESC, id DESC",
            [$user]
        )));
    }

    public function getMessage(int $id): ?array
    {
        $message = $this->database->fetch("SELECT * FROM private_messages WHERE id = ?", [$id]);

        return $message ? $this->format($message) : null;
    }

    public function getUnreadCount(int $user): int
    {
        return (int)$this->database->result(
            "SELECT COUNT(*) FROM private_messages WHERE recipient = ? AND deleted_by_recipient = 0 AND read_date IS NULL",
            [$user]
        );
    }

    private function format(array $message): array
    {
        $author = new UserData($this->database, $message["author"]);
        $recipient = new UserData($this->database, $message["recipient"]);

        return [
            "id" => $message["id"],
            "reply_to" => $message["reply_to_id"],
            "title" => $message["title"],
            "contents" => $message["contents"],
            "published" => $message["date"],
            "read" => $message["read_date"],
            "author" => [
                "id" => $message["author"],
                "info" => $author->getUserArray(),
            ],
            "recipient" => [
                "id" => $message["recipient"],
                "info" => $recipient->getUserArray(),
            ],
        ];
    }
}
