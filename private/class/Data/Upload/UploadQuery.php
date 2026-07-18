<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

namespace Data\Upload;

use Core\SquareBracket;
use Core\Database;
use Core\Authentication;

use Data\User\UserFlags;

/**
 * class UploadQuery
 *
 * This class allows for uniform upload-related queries within the codebase.
 */
class UploadQuery
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var Authentication
     */
    private Authentication $auth;

    /**
     * @var string
     */
    private string $whereRatings;

    /**
     * @var array
     */
    private array $whereTagBlacklist;

    /**
     * @var int
     */
    private int $userFlags;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return void
     */
    public function __construct(SquareBracket $sb)
    {
        $this->database = $sb->getDatabaseClass();
        $this->auth = $sb->getAuthenticationClass();
        $this->whereRatings = $sb->getAuthenticationClass()->databaseWhereRatingsHelper();
        $this->whereTagBlacklist = $sb->getAuthenticationClass()->databaseWhereTagBlacklistHelper();
        if ($this->auth->isLoggedIn()) {
            $this->userFlags = $this->auth->getUserFlags();
        } else {
            $this->userFlags = 0;
        }
    }

    /**
     * function query
     *
     * @param string $order
     * @param int $limit
     * @param string $whereCondition
     * @param array $params
     * @param bool $adminPanel
     *
     * @return UploadResult
     */
    public function query($order = null, $limit = null, $whereCondition = null, $params = [], $adminPanel = false): UploadResult
    {
        $query = "SELECT v.*, COALESCE(NULLIF(original_timestamp, 0), `timestamp`) AS uploaded FROM uploads v";
        $whereClauses = [];

        if (!$adminPanel) {
            // if upload isnt taken down
            $whereClauses[] = "v.upload_id NOT IN (SELECT upload FROM upload_takedowns)";
            // if upload does not belong to someone who has been banned
            $whereClauses[] = "v.author NOT IN (SELECT user FROM user_bans)";
            // if upload is public
            $whereClauses[] = "v.visibility = " . UploadVisibilityEnum::Public->value;
        }

        if (!$this->auth->isLoggedIn()) {
            $blocked_guest_flag = UploadFlags::FLAG_BLOCK_GUESTS->value;

            $whereClauses[] = "v.flags & $blocked_guest_flag != $blocked_guest_flag";
        }

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!$adminPanel) {
            if (!($this->userFlags & UserFlags::FLAG_MATURE_CONTENT_ACCESS->value)) {
                $matureFlag = UploadFlags::FLAG_MATURE->value;

                $whereClauses[] = "v.flags & $matureFlag != $matureFlag";
            }

            // OLD RATINGS (this will be removed later)
            if (!empty($this->whereRatings)) {
                $whereClauses[] = $this->whereRatings;
            }

            if (!empty($this->whereTagBlacklist['sql'])) {
                $whereClauses[] = $this->whereTagBlacklist['sql'];
                // tag blacklist ?'s are appended after $whereCondition in the final
                // query string, so their params must be appended after $params too -chaziz 07/16/2026
                $params = array_merge($params, $this->whereTagBlacklist['params']);
            }
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        if ($order !== null) {
            $query .= " ORDER BY $order";
        }
        
        if ($limit !== null) {
            // compatibility with Core\Database::paginate()
            $query .= str_contains((string)$limit, "LIMIT") ? " $limit" : " LIMIT $limit";
        }

        return new UploadResult($this->database, $this->database->fetchArray($this->database->query($query, $params)));
    }

    /**
     * function count
     * 
     * used in the browse page
     *
     * @param mixed $whereCondition
     * @param mixed $params
     *
     * @return mixed
     */
    public function count($whereCondition = null, $params = [])
    {
        $query = "SELECT COUNT(*) FROM uploads v";
        $whereClauses = [];

        //if (!$adminPanel) {
        // if upload isnt taken down
        $whereClauses[] = "v.upload_id NOT IN (SELECT upload FROM upload_takedowns)";
        // if upload does not belong to someone who has been banned
        $whereClauses[] = "v.author NOT IN (SELECT user FROM user_bans)";
        $whereClauses[] = "v.visibility = " . UploadVisibilityEnum::Public->value;
        //}

        if (!$this->auth->isLoggedIn()) {
            $blocked_guest_flag = UploadFlags::FLAG_BLOCK_GUESTS->value;

            $whereClauses[] = "v.flags & $blocked_guest_flag != $blocked_guest_flag";
        }

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!($this->userFlags & UserFlags::FLAG_MATURE_CONTENT_ACCESS->value)) {
            $matureFlag = UploadFlags::FLAG_MATURE->value;

            $whereClauses[] = "v.flags & $matureFlag != $matureFlag";
        }

        // OLD RATINGS (this will be removed later)
        if (!empty($this->whereRatings)) {
            $whereClauses[] = $this->whereRatings;
        }

        if (!empty($this->whereTagBlacklist['sql'])) {
            $whereClauses[] = $this->whereTagBlacklist['sql'];
            // tag blacklist ?'s are appended after $whereCondition in the final
            // query string, so their params must be appended after $params too -chaziz 07/16/2026
            $params = array_merge($params, $this->whereTagBlacklist['params']);
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        return $this->database->result($query, $params);
    }
}
