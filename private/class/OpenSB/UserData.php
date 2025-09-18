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

use BluffingoCore\Database;

class UserData
{
    private Database $database;
    private $id;
    private $data;

    private static $userDataCache = [];

    public function __construct(Database $database, $id)
    {
        $this->database = $database;
        $this->id = $id;

        // check if user data has already been cached
        if (isset(self::$userDataCache[$id])) {
            $this->data = self::$userDataCache[$id];
            return;
        } else {
            // otherwise fetch the data from the db
            $this->data = $this->database->fetch(
                "SELECT id, name, title, userlink_color, joined, last_seen, powerlevel, flags FROM users WHERE id = ?",
                [$id]
            );
        }

        if ($this->data == null) {
            trigger_error("User ID $id is nonexistent.", E_USER_WARNING);
        } else {
            // cache the data
            self::$userDataCache[$id] = $this->data;
        }
    }

    public function isUserBanned(): bool
    {
        // also cache if a user is banned (for later)
        if (isset(self::$userDataCache["banned_$this->id"])) {
            return self::$userDataCache["banned_$this->id"];
        }

        $isBanned = (bool) $this->database->fetch(
            "SELECT * FROM user_bans WHERE user = ?",
            [$this->id]
        );

        self::$userDataCache["banned_$this->id"] = $isBanned;
        return $isBanned;
    }

    public function getUserArray(): array
    {
        if ($this->data) {
            return [
                "username" => $this->data["name"],
                "displayname" => $this->data["title"],
                "color" => $this->data["userlink_color"],
                "joined" => $this->data["joined"],
                "connected" => $this->data["last_seen"],
                // TODO: rename powerlevel to role and make this use the strings on UserRoleEnum
                "powerlevel" => $this->data["powerlevel"],
                "flags" => UserFlags::toArray($this->data["flags"]), // stupid i think
            ];
        } else {
            return [
                "username" => "InvalidUser!",
                "displayname" => "Invalid user!",
                "color" => "#FF0000",
                "joined" => 0,
                "connected" => 0,
                "powerlevel" => 1,
            ];
        }
    }

    public static function getUserDataCache(): array
    {
        return self::$userDataCache;
    }
}
