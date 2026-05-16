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

namespace Data\Journal;

use Core\SquareBracket;
use Core\Database;
use Core\Authentication;

use Data\User\UserFlags;

/**
 * class JournalQuery
 *
 * This class allows for uniform journal-related queries within the codebase.
 */
class JournalQuery
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
     * @var int
     */
    private int $userFlags;

    /**
     * @var bool
     */
    private bool $isFulpTube;

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
        if ($this->auth->isLoggedIn()) {
            $this->userFlags = $this->auth->getUserFlags();
        } else {
            $this->userFlags = 0;
        }

        $this->isFulpTube = $sb->isFulpTubeMode();
    }

    /**
     * function query
     *
     * @param string $order
     * @param int $limit
     * @param string $whereCondition
     * @param array $params
     *
     * @return JournalResult
     */
    public function query($order, $limit, $whereCondition = null, $params = []): JournalResult
    {
        $query = "SELECT j.* FROM journals j";
        $whereClauses = [];

        /*
        if (!$this->auth->isLoggedIn()) {
            $blocked_guest_flag = JournalFlags::FLAG_BLOCK_GUESTS->value;
            $whereClauses[] = "j.flags & $blocked_guest_flag != $blocked_guest_flag";
        }
        */

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

        return new JournalResult($this->database, $this->database->fetchArray($this->database->query($query, $params)), $this->isFulpTube);
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
        $query = "SELECT COUNT(*) FROM journals j";
        $whereClauses = [];

        /*
        if (!$this->auth->isLoggedIn()) {
            $blocked_guest_flag = JournalFlags::FLAG_BLOCK_GUESTS->value;
            $whereClauses[] = "j.flags & $blocked_guest_flag != $blocked_guest_flag";
        }
        */

        if (!empty($whereCondition)) {
            $whereClauses[] = $whereCondition;
        }

        if (!empty($whereClauses)) {
            $query .= " WHERE " . implode(" AND ", $whereClauses);
        }

        return $this->database->result($query, $params);
    }
}
