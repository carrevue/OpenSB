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

namespace OpenSB;

use OpenSB\SquareBracket;
use BluffingoCore\Database;

/**
 * class UploadQuery
 *
 * UploadQuery
 * This class allows for uniform upload-related queries within the codebase.
 */
class UploadQuery
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var string
     */
    private string $whereRatings;

    /**
     * @var string
     */
    private string $whereTagBlacklist;

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
        $this->whereRatings = $sb->getAuthenticationClass()->databaseWhereRatingsHelper();
        $this->whereTagBlacklist = $sb->getAuthenticationClass()->databaseWhereTagBlacklistHelper();
    }

    /**
     * function query
     *
     * @param mixed $order
     * @param mixed $limit
     * @param mixed $whereCondition
     * @param mixed $params
     * @param mixed $adminPanel
     *
     * @return mixed
     */
    public function query($order, $limit, $whereCondition = null, $params = [], $adminPanel = false)
    {
        global $auth;

        $query = "SELECT v.* FROM uploads v";
        $whereClauses = [];

        if (!$adminPanel) {
            // if upload isnt taken down
            $whereClauses[] = "v.upload_id NOT IN (SELECT upload FROM upload_takedowns)";
            // if upload does not belong to someone who has been banned
            $whereClauses[] = "v.author NOT IN (SELECT user FROM user_bans)";
        }

        if (!$auth->isUserLoggedIn()) {
            $blocked_guest_flag = UploadFlags::FLAG_BLOCK_GUESTS->value;

            $whereClauses[] = "v.flags & $blocked_guest_flag != $blocked_guest_flag";
        }

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!$adminPanel) {
            if (!empty($this->whereRatings)) {
                $whereClauses[] = $this->whereRatings;
            }

            if (!empty($this->whereTagBlacklist)) {
                $whereClauses[] = $this->whereTagBlacklist;
            }
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        if (str_contains($limit, "LIMIT")) {
            // compatibility with BluffingoCore\Database::paginate()
            $query .= " ORDER BY $order $limit";
        } else {
            $query .= " ORDER BY $order LIMIT $limit";
        }

        return $this->database->fetchArray($this->database->query($query, $params));
    }

    // used in the browse page

    /**
     * function count
     *
     * @param mixed $whereCondition
     * @param mixed $params
     *
     * @return mixed
     */
    public function count($whereCondition = null, $params = [])
    {
        $query = "
        SELECT COUNT(*)
        FROM uploads v
        WHERE v.upload_id NOT IN (SELECT upload FROM upload_takedowns)
        AND v.author NOT IN (SELECT user FROM user_bans)
        ";

        if (!empty($whereCondition)) {
            $query .= "AND $whereCondition ";
        }

        if (!empty($this->whereRatings)) {
            $query .= "AND $this->whereRatings ";
        }

        if (!empty($this->whereTagBlacklist)) {
            $query .= "AND $this->whereTagBlacklist ";
        }

        return $this->database->result($query, $params);
    }
}
