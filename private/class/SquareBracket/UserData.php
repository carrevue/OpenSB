<?php

namespace SquareBracket;

class UserData
{
    private \SquareBracket\Database $database;
    private $id;

    private $data;

    public function __construct(\SquareBracket\Database $database, $id)
    {
        $this->database = $database;
        $this->id = $id;

        $this->data = $this->database->fetch("SELECT id, name, title, customcolor, joined, lastview, customcolor 
        FROM users WHERE id = ?", [$id]);

        if ($this->data == null) {
            trigger_error("User ID $id is nonexistent.", E_USER_WARNING);
        }
    }

    public function isUserBanned()
    {
        if ($this->database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$this->id])) { return true; }
        return false;
    }

    /*
    private function getUserFollowerCount() {
        return $this->database->fetch("SELECT COUNT(user) FROM user_follows WHERE user = ?",
            [$this->id])['COUNT(user)'];
    }
    */

    public function getUserArray(): array
    {
        if ($this->data) {
            return [
                "username" => $this->data["name"],
                "displayname" => $this->data["title"],
                "color" => $this->data["customcolor"],
                //"followers" => $this->getUserFollowerCount(),
                "joined" => $this->data["joined"],
                "connected" => $this->data["lastview"],
                "customcolor" => $this->data["customcolor"],
            ];
        } else {
            return [
                "username" => "InvalidUser!",
                "displayname" => "Invalid user!",
                "color" => "#FF0000",
                "followers" => 0,
                "joined" => 0,
                "connected" => 0,
                "customcolor" => "#FF0000",
            ];
        }
    }
}