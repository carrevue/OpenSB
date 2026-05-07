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

namespace Data\User;

use Core\SquareBracket;
use Core\Database;

/**
 * class UserQuery
 *
 * This class allows for uniform user-related queries within the codebase.
 */
class UserQuery
{
    /**
     * @var Database
     */
    private Database $database;

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
    }

    /**
     * function query
     *
     * @param string $order
     * @param int $limit
     * @param string $whereCondition
     * @param array $params
     *
     * @return UserResult
     */
    public function query($order, $limit, $whereCondition = null, $params = [])
    {
        $query = "SELECT u.id, u.about, u.title, u.flags, u.joined, u.last_seen, u.f_index, u.u_index FROM users u";
        $whereClauses = [];
        $baseParams = [UserFlags::FLAG_UNVERIFIED->value];

        $whereClauses[] = "u.id NOT IN (SELECT user FROM user_bans)";
        $whereClauses[] = "(u.flags & ?) = 0";

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        $query .= " WHERE " . implode(" AND ", $whereClauses);
        $query .= " ORDER BY $order";
        $allParams = array_merge($baseParams, $params);

        if (str_contains((string) $limit, "LIMIT")) {
            $query .= " $limit";
        } else {
            $query .= " LIMIT " . (int) $limit;
        }

        return new UserResult($this->database, $this->database->fetchArray($this->database->query($query, $allParams)));
    }

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
        $query = "SELECT COUNT(*) FROM users u";
        $whereClauses = [];
        $baseParams = [UserFlags::FLAG_UNVERIFIED->value];

        $whereClauses[] = "u.id NOT IN (SELECT user FROM user_bans)";
        $whereClauses[] = "(u.flags & ?) = 0";

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        $query .= " WHERE " . implode(" AND ", $whereClauses);
        $allParams = array_merge($baseParams, $params);

        return $this->database->result($query, $allParams);
    }
}
