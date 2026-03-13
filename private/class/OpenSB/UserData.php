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

namespace OpenSB;

use Core\Database;
use OpenSB\FakeUser;
use Core\Utilities;

/**
 * class UserData
 * 
 * This class handles user info data in an uniform and consistent way, which 
 * can be reused on pages. It automatically caches every single user thats
 * required by a page, preventing redudant database queries from being done.
 */
class UserData
{
    /**
     * @var Database The database class
     */
    private Database $database;

    /**
     * @var int The user's ID
     */
    private int $id;

    /**
     * @var array The user's data
     */
    private array $data;

    /**
     * @var mixed The user data cache
     */
    private static array $userDataCache = [];

    /**
     * function __construct
     *
     * @param Database $database
     * @param mixed $id
     *
     * @return void
     */
    public function __construct(Database $database, $id)
    {
        $this->database = $database;
        $this->id = $id;

        // check if user data has already been cached before doing anything
        if (isset(self::$userDataCache[$id])) {
            $this->data = self::$userDataCache[$id];
            return;
        }
        
        if ($id < 0) {
            $data = FakeUser::getFakeUserFromID($id);
        } else {
            // fetch the data from the db
            $data = $this->database->fetch(
                "SELECT id, name, title, userlink_color, joined, last_seen, powerlevel, flags FROM users WHERE id = ?",
                [$id]
            );
        }

        if (!$data) {
            //trigger_error("User ID $id is nonexistent.", E_USER_WARNING);
            // fallback to generic data
            $data = [
                "name" => "InvalidUser!",
                "title" => "Invalid user! ($id)",
                "userlink_color" => "#FF00FF",
                "joined" => 1,
                "last_seen" => 1,
                "powerlevel" => 0,
                "flags" => 0, // we don't need any flags to be set.
                "following" => false,
            ];   
        }

        $this->data = $data;
        $this->data["following"] = Utilities::isFollowingUser($id);

        // cache the data
        self::$userDataCache[$id] = $this->data;
    }

    /**
     * function isUserBanned
     * 
     * Checks if the user is banned.
     *
     * @return bool
     */
    public function isUserBanned(): bool
    {
        // also cache if a user is banned (if needed in the future)
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

    /**
     * function getUserArray
     * 
     * Returns the user data array.
     *
     * @return array
     */
    public function getUserArray(): array
    {
        return [
            "username" => $this->data["name"],
            "displayname" => $this->data["title"],
            "color" => $this->data["userlink_color"],
            "joined" => $this->data["joined"],
            "connected" => $this->data["last_seen"],
            "following" => $this->data["following"],
            // TODO: rename powerlevel to role and make this use the strings on UserRoleEnum
            "powerlevel" => $this->data["powerlevel"],
            "flags" => UserFlags::toArray($this->data["flags"]), // stupid i think
        ];
    }

    /**
     * function getUserDataCache
     * 
     * Returns the user data caches for debugging purposes.
     *
     * @return array
     */
    public static function getUserDataCache(): array
    {
        return self::$userDataCache;
    }
}
