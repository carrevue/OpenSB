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

namespace SquareBracket;

class UploadQuery
{
    private $database;
    private $whereRatings;
    private $whereTagBlacklist;

    public function __construct($database)
    {
        $this->database = $database;
        $this->whereRatings = Utilities::whereRatings();
        $this->whereTagBlacklist = Utilities::whereTagBlacklist();
    }

    public function query($order, $limit, $whereCondition = null, $params = [], $adminPanel = false)
    {
        global $auth;

        $query = "SELECT v.* FROM uploads v";
        $whereClauses = [];

        if (!$adminPanel) {
            // if upload isnt taken down
            $whereClauses[] = "v.video_id NOT IN (SELECT submission FROM upload_takedowns)";
            // if upload does not belong to someone who has been banned
            $whereClauses[] = "v.author NOT IN (SELECT userid FROM user_bans)";
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

        $query .= " ORDER BY $order LIMIT $limit";

        return $this->database->fetchArray($this->database->query($query, $params));
    }

    // used in the browse page
    public function count($whereCondition = null, $params = [])
    {
        $query = "
        SELECT COUNT(*)
        FROM uploads v
        WHERE v.video_id NOT IN (SELECT submission FROM upload_takedowns)
        AND v.author NOT IN (SELECT userid FROM user_bans)
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
