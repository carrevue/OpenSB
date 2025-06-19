<?php

namespace SquareBracket;

class UserCustomizationData
{
    private Database $database;
    private $data;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->data = $this->database->fetch(
            "SELECT * FROM user_profile_customization WHERE user = ?",
            [$id]
        );
    }

    // i'm not sure if this should be array|false or ?array
    public function getData(): array|false {
        if ($this->data) {
            return $this->data;
        } else {
            return false;
        }
    }
}