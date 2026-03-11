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

namespace OpenSB;

use OpenSB\Database;
use OpenSB\UserData;

/**
 * class PostData
 *
 * This class handles post data in an uniform and consistent way, which can 
 * be reused on pages.
 */
class PostData
{
    /**
     * @var Database The database class
     */
    private Database $database;

    /**
     * @var UserData The journal's author
     */
    private UserData $author;

    /** 
     * @var array|false|null Post data 
     */
    private array|false|null $data = null;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->data = $this->database->fetch("SELECT p.* FROM posts p WHERE p.id = ?", [$id]);

        if ($this->data != []) {
            $this->author = new UserData($database, $this->data["author"]);
        }
    }

    /**
     * Get the post data.
     *
     * @return array|null Array containing post data, or null if not found.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the post author's data.
     *
     * @return array
     */
    public function getAuthorData()
    {
        return $this->author->getUserArray();
    }

    /**
     * Check whether this post's author is banned.
     *
     * @return bool
     */
    public function isTakenDown()
    {
        return $this->author->isUserBanned();
    }
}