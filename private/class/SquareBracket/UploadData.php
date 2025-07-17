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

namespace SquareBracket;

use BluffingoCore\Database;

/**
 * uploads.
 */
class UploadData
{
    private Database $database;
    private UploadRatingData $ratings;
    private $takedown;
    private $data;
    private $tags;
    private $deleted_data;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->deleted_data = $this->database->fetch("SELECT COUNT(*) FROM upload_deleted v WHERE id = ?", [$id])["COUNT(*)"];

        // if we get the internal id instead of the string id, we correct $id after fetching the upload otherwise
        // stuff won't work.
        if (is_int($id)) {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.id = ?", [$id]);
            if ($this->data != []) {
                $id = $this->data["video_id"];
            }
        } else {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.video_id = ?", [$id]);
        }
        if ($this->data != []) {
            $this->takedown = $this->database->fetchArray($this->database->query("SELECT * FROM upload_takedowns t WHERE t.submission = ?", [$id]));
            $this->tags = $this->database->fetchArray($this->database->query("SELECT * FROM `upload_tag_index` ti JOIN upload_tag_meta t ON (t.tag_id = ti.tag_id) WHERE ti.video_id = ?", [$this->data["id"]]));
            $this->ratings = new UploadRatingData($database, $this->data["id"]);
        }
    }

    public function getTakedown()
    {
        return $this->takedown;
    }

    public function getData()
    {
        return $this->data;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function isDeleted()
    {
        return $this->deleted_data;
    }

    public function getRatingData()
    {
        return $this->ratings->calculateRatingData();
    }

    public function getUploadFlagsArray()
    {
        return UploadFlags::toArray($this->data["flags"]);
    }
}
