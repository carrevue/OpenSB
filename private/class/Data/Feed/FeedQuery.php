<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Data\Feed;

use Core\SquareBracket;
use Core\Database;
use Core\Authentication;

/**
 * class FeedQuery
 * 
 * Gets the feed data. This is a hybrid of UploadQuery and PostQuery.
 */
class FeedQuery
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
     * @return array
     */
    public function query($order, $limit, $whereCondition = null, $params = [], $adminPanel = false)
    {
        $query = "SELECT p.* FROM posts p";
        $whereClauses = [];

        if (!$adminPanel) {
            // if author isn't banned
            $whereClauses[] = "p.author NOT IN (SELECT user FROM user_bans)";
        }

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        if (str_contains($limit, "LIMIT")) {
            // compatibility with Core\Database::paginate()
            $query .= " ORDER BY $order $limit";
        } else {
            $query .= " ORDER BY $order LIMIT $limit";
        }
    }
}