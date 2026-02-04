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
 * class UploadCollectionData
 *
 * This class handles upload collection data.
 */
class CollectionData
{
    /**
     * @var Database The database class
     */
    private Database $database;

    /**
     * @var UserData The collection's author
     */
    private ?UserData $author;

    /** 
     * @var array|false|null Collection data 
     */
    private array|false|null $data = null;

    /**
     * function __construct
     *
     * @param Database $database
     * @param string $id
     *
     * @return void
     */
    public function __construct(Database $database, string $id)
    {
        $this->database = $database;
        $this->data = [
            "title" => "Collection Title",
            "description" => "This is a collection of uploads.",
            "author" => 1,
            "timestamp" => 0,
            "timestamp_updated" => time(),
            "uploads" => [
                1, 2, 3, 4, 5, // temporary, this will be kind of tricky to implement LOL
            ],
        ];

        if ($this->data != []) {
            $this->author = new UserData($database, $this->data["author"]);
        }
    }

    /**
     * Get the collection data.
     *
     * @return array|null Array containing collection data, or null if not found.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the collection author's data.
     *
     * @return array
     */
    public function getAuthorData()
    {
        return $this->author->getUserArray();
    }
}