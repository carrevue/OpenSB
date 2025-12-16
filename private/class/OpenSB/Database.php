<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021-2023 ROllerozxa

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

use Exception;
use PDO;

/**
 * PDO interface(?).
 */
class Database
{
    private $sql;
    private $queryLog = [];
    private $profilingEnabled = false;

    /**
     * @throws Exception
     */
    public function __construct($host, $user, $pass, $db)
    {
        // ok this is fucking dumb and i don't know if this is going to fuck out on 8.3 and older.
        if (version_compare(PHP_VERSION, '8.4.0') >= 0) {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO\Mysql::ATTR_INIT_COMMAND => 'SET sql_mode="TRADITIONAL"'
            ];

            $this->sql = new PDO\Mysql("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);
        } else {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="TRADITIONAL"'
            ];

            $this->sql = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);    
        }
    }

    public function result($query, $params = [])
    {
        $res = $this->query($query, $params);
        return $res->fetchColumn();
    }

    public function query($query, $params = [])
    {
        $startTime = 0;
        $executionTime = 0;

        if ($this->profilingEnabled) {
            $startTime = microtime(true);
        }

        $res = $this->sql->prepare($query);
        $res->execute($params);

        if ($this->profilingEnabled) {
            $executionTime = microtime(true) - $startTime;

            $this->logQueryForProfiler($query, $params, $startTime, $executionTime);
        }

        return $res;
    }

    public function fetchArray($query): array
    {
        $out = [];
        while ($record = $query->fetch()) {
            $out[] = $record;
        }
        return $out;
    }

    public function fetch($query, $params = [])
    {
        $res = $this->query($query, $params);
        return $res->fetch();
    }

    public function insertId()
    {
        return $this->sql->lastInsertId();
    }

    /**
     * Helper function to insert a row into a table.
     */
    public function insertInto($table, $data, $dry = false)
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $field => $value) {
            $fields[] = $field;
            $placeholders[] = '?';
            $values[] = $value;
        }

        /*
        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
        $table, commasep($fields), commasep($placeholders));
        */

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(',', $fields),
            implode(',', $placeholders)
        );

        if ($dry)
            return $query;
        else
            return $this->query($query, $values);
    }

    /**
     * Helper function to construct part of a query to set a lot of fields in one row
     */
    public function updateRowQuery($fields)
    {
        // Temp variables for dynamic query construction.
        $fieldquery = '';
        $placeholders = [];

        // Construct a query containing all fields.
        foreach ($fields as $fieldk => $fieldv) {
            if ($fieldquery) $fieldquery .= ',';
            $fieldquery .= $fieldk . '=?';
            $placeholders[] = $fieldv;
        }

        return ['fieldquery' => $fieldquery, 'placeholders' => $placeholders];
    }

    public function paginate($page, $pp)
    {
        $page = (is_numeric($page) && $page > 0 ? $page : 1);

        // if its too high just set it back to 1 to avoid a database error.
        // THIS IS BY DESIGN. -chaziz 9/13/2025
        if ($page > 2147483647) {
            $page = 1;
        }

        return sprintf(" LIMIT %s, %s", (($page - 1) * $pp), $pp);
    }

    public function getServerVersion()
    {
        return $this->sql->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    private function logQueryForProfiler($query, $params, $startTime, $executionTime)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $immediateCaller = $backtrace[0] ?? [];
        $actualCaller = $backtrace[1] ?? [];

        // check if the caller isnt right here for queries done through fetch and fetchArray
        $caller = (str_ends_with($immediateCaller['file'] ?? '', 'Database.php'))
            ? $actualCaller
            : $immediateCaller;

        // remove root path so we have a shorter string
        $file = str_replace(BLUFF_ROOT_PATH, '', $caller['file'] ?? '');

        $callerInfo = [
            'file' => $file ?? 'unknown',
            'line' => $caller['line'] ?? 'unknown',
            'function' => $caller['function'] ?? 'unknown',
        ];

        $this->queryLog[] = [
            'query' => $query,
            'params' => $params,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true),
            'caller_info' => $callerInfo,
        ];
    }

    public function setProfiling(bool $enabled): void
    {
        $this->profilingEnabled = $enabled;
    }

    public function getQueryLog(): array
    {
        if (!$this->profilingEnabled) {
            return [];
        }

        return $this->queryLog;
    }

    /**
     * IMPORTANT: DO NOT CALL THIS FUNCTION OUTSIDE OF PROFILER. IF YOU NEED THE DATABASE PROFILING REPORT.
     * GET THAT SHIT THROUGH THE PROFILER CLASS' getDatabaseProfilerInfo FUNCTION (because then youll get
     * the full data). -chaziz -4/12/2025
     */
    public function getProfilingReport(): array
    {
        if (!$this->profilingEnabled) {
            return [];
        }

        $report = [
            'total_queries' => count($this->queryLog),
            'total_time' => 0,
            'queries' => [],
            'slowest_query' => null,
            'fastest_query' => null,
        ];

        if (empty($this->queryLog)) {
            return $report;
        }

        $slowest = $this->queryLog[0];
        $fastest = $this->queryLog[0];

        foreach ($this->queryLog as $query) {
            $report['total_time'] += $query['execution_time'];

            // find the slowest and fastest queries
            if ($query['execution_time'] > $slowest['execution_time']) {
                $slowest = $query;
            }

            if ($query['execution_time'] < $fastest['execution_time']) {
                $fastest = $query;
            }

            $report['queries'][] = [
                'query' => $query['query'],
                'time' => $query['execution_time'],
                'params' => $query['params'],
                'caller_info' => $query['caller_info'],
            ];
        }

        $report['slowest_query'] = $slowest;
        $report['fastest_query'] = $fastest;
        $report['average_time'] = $report['total_time'] / $report['total_queries'];

        return $report;
    }
}
