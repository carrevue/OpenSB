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

namespace Data\Post;

use Core\SquareBracket;
use Core\Database;

/**
 * class PostQuery
 *
 * This class allows for uniform post-related queries within the codebase.
 */
class PostQuery
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var \Core\Authentication
     */
    private \Core\Authentication $auth;

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
     * @return PostResult
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

        return new PostResult($this->database, $this->database->fetchArray($this->database->query($query, $params)));
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
        $query = "SELECT COUNT(*) FROM posts p";
        $whereClauses = [];

        // if author isn't banned
        $whereClauses[] = "p.author NOT IN (SELECT user FROM user_bans)";

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        return $this->database->result($query, $params);
    }
}
