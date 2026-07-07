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

namespace Data\Playlist;

use Core\Database;
use Data\User\UserData;

/**
 * class PlaylistData
 *
 * This class handles upload playlist data.
 */
class PlaylistData
{
    /**
     * @var Database The database class
     */
    private Database $database;

    /**
     * @var UserData The playlist's author
     */
    private ?UserData $author;

    /** 
     * @var array|false|null Playlist data 
     */
    private array|false|null $data = null;

    /** 
     * @var array|false|null List of uploads in the playlist
     */
    private array|false|null $uploads = null;

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
        /*
        $this->data = [
            "title" => "Playlist Title",
            "description" => "This is a playlist of uploads.",
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
        */
        
        $this->data = $this->database->fetch("SELECT * FROM playlists WHERE playlist_id = ?", [$id]);

        if ($this->data != []) {
            $this->uploads = array_column(
                $this->database->fetchArray($this->database->query("SELECT upload FROM playlist_items WHERE playlist = ?", [$this->data["id"]])),
                'upload'
            );
        }
    }

    /**
     * Get the playlist data.
     *
     * @return array|null Array containing playlist data, or null if not found.
     */
    public function getData()
    {
        return $this->data ?? [];
    }

    /**
     * Get the list of uploads in the playlist.
     *
     * @return array|null Array of upload integer IDs, or null if not found.
     */
    public function getUploads()
    {
        return $this->uploads ?? [];
    }

    /**
     * Get the playlist author's data.
     *
     * @return array
     */
    public function getAuthorData()
    {
        return $this->author->getUserArray();
    }
}