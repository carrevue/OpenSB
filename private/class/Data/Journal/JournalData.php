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

namespace Data\Journal;

use Core\Database;
use Data\User\UserData;

/**
 * class JournalData
 *
 * This class handles journal data in an uniform and consistent way, which can 
 * be reused on pages.
 */
class JournalData
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
     * @var array|false|null Journal data 
     */
    private array|false|null $data = null;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->data = $this->database->fetch("SELECT j.* FROM journals j WHERE j.id = ?", [$id]);

        if ($this->data != []) {
            $this->author = new UserData($database, $this->data["author"]);
        }
    }

    /**
     * Get the journal data.
     *
     * @return array|null Array containing journal data, or null if not found.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the journal author's data.
     *
     * @return array
     */
    public function getAuthorData()
    {
        return $this->author->getUserArray();
    }

    /**
     * Check whether this journal's author is banned.
     *
     * @return bool
     */
    public function isTakenDown()
    {
        return $this->author->isUserBanned();
    }
}