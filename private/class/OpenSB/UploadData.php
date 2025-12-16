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

namespace OpenSB;

use OpenSB\Database;

/**
 * class UploadData
 *
 * This class handles upload data in an uniform and consistent way, which can 
 * be reused on pages.
 */
class UploadData
{
    /**
     * @var Database The database class
     */
    private Database $database;


    /**
     * @var UploadRatingData|null The Upload rating data helper class.
     */
    private ?UploadRatingData $ratings = null;

    /** 
     * @var array|false|null Upload takedown data
     */
    private array|null $takedown = null;

    /** 
     * @var array|false|null Upload data 
     */
    private array|false|null $data = null;

    /** 
     * @var array|false|null Tags associated with this upload
     */
    private array|false|null $tags = null;

    /** 
     * @var bool Indicates if the upload was deleted as a failsafe measure
     */
    private bool $is_deleted = false;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->is_deleted = $this->database->result("SELECT COUNT(*) FROM upload_deleted v WHERE id = ?", [$id]);

        // if we get the internal id instead of the string id, we correct $id after fetching the upload otherwise
        // stuff won't work.
        if (is_int($id)) {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.id = ?", [$id]);
            if ($this->data != []) {
                $id = $this->data["upload_id"];
            }
        } else {
            $this->data = $this->database->fetch("SELECT v.* FROM uploads v WHERE v.upload_id = ?", [$id]);
        }

        if ($this->data != []) {
            $this->takedown = $this->database->fetchArray($this->database->query("SELECT * FROM upload_takedowns t WHERE t.upload = ?", [$id]));
            $this->tags = $this->database->fetchArray($this->database->query("SELECT * FROM `upload_tag_index` ti JOIN upload_tag_meta t ON (t.tag_id = ti.tag_id) WHERE ti.upload_id = ?", [$this->data["id"]]));
            $this->ratings = new UploadRatingData($database, $this->data["id"]);
        }
    }

    /**
     * Get takedown data for this upload.
     *
     * @return array|null Array containing takedown data, or null if not found.
     */
    public function getTakedown()
    {
        return $this->takedown;
    }

    /**
     * Get the upload data.
     *
     * @return array|null Array containing upload data, or null if not found.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the tags associated with the upload.
     *
     * @return array|null Array of tags, or null if none or upload not found.
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Check whether this upload has been deleted.
     *
     * @return bool True if the upload has been deleted.
     */
    public function isDeleted()
    {
        return $this->is_deleted;
    }

    /**
     * Calculate rating data using the `UploadRatingData` class
     *
     * @return array|null Rating data or null.
     */
    public function getRatingData()
    {
        if ($this->ratings === null) {
            return null;
        }
        return $this->ratings->calculateRatingData();
    }

    /**
     * Calculate rating data using the `UploadRatingData` class
     * 
     * @note this is kind of awkward i'd say. try to merge this into getRatingData.
     *
     * @return array|null Rating data or null.
     */
    public function getRatingDataAsLikeRatio()
    {
        if ($this->ratings === null) {
            return null;
        }
        return $this->ratings->calculateRatingDataAsLikeRatio();
    }

    /**
     * Returns of array of specific upload flags.
     *
     * @return array Upload flag array data.
     */
    public function getUploadFlagsArray()
    {
        return UploadFlags::toArray($this->data["flags"]);
    }
}
