<?php

namespace SquareBracket;

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
                "SELECT id, name, title, customcolor, joined, lastview, powerlevel, u_flags FROM users WHERE id = ?",
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
            "SELECT * FROM user_bans WHERE userid = ?",
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
                "color" => $this->data["customcolor"],
                "joined" => $this->data["joined"],
                "connected" => $this->data["lastview"],
                "powerlevel" => $this->data["powerlevel"], // TODO: rename powerlevel to something better
                "flags" => UserFlags::toArray($this->data["u_flags"]), // stupid i think
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

    public static function getUserDataCache(): array {
        return self::$userDataCache;
    }
}