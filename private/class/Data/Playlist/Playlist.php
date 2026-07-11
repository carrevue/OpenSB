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

use Core\SquareBracket;
use Core\Database;
use Data\Upload\UploadQuery;
use Data\User\UserData;

/**
 * class Playlist
 *
 * This class handles playlists.
 */
class Playlist
{
    /**
     * @var SquareBracket
     */
    private SquareBracket $sb;

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
     * @param SquareBracket $sb
     * @param string $id
     *
     * @return void
     */
    public function __construct(SquareBracket $sb, string $id)
    {
        $this->sb = $sb;
        $this->database = $sb->getDatabaseClass();
        
        $this->data = $this->database->fetch("SELECT * FROM playlists WHERE playlist_id = ?", [$id]);

        if ($this->data != []) {
            $this->uploads = array_column(
                $this->database->fetchArray($this->database->query("SELECT upload FROM playlist_items WHERE playlist = ? ORDER BY position", [$this->data["id"]])),
                'upload'
            );

            $this->author = new UserData($this->database, $this->data["author"]);
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
    public function getUploads($limit = 100)
    {
        if ($this->uploads === null) {
            return [];
        }

        $query = implode(', ', $this->uploads);

        $upload_query = new UploadQuery($this->sb);
        return $upload_query->query(
            sprintf("FIELD(v.id, %s)", implode(",", array_map('intval', $this->uploads))),
            $limit,
            sprintf("v.id in (%s)", $query)
        )->toCleanArray();
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