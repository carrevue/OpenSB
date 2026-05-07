<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

namespace Core;

use Exception;
use PDO;
use PDOStatement;

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

    public function result(string $query, array $params = [])
    {
        $res = $this->query($query, $params);
        return $res->fetchColumn();
    }

    public function query(string $query, array $params = []): bool|PDOStatement
    {
        $startTime = $this->profilingEnabled ? microtime(true) : 0;

        $res = $this->sql->prepare($query);
        $res->execute($params);

        if ($this->profilingEnabled) {
            $this->logQueryForProfiler($query, $params, $startTime, microtime(true) - $startTime);
        }

        return $res;
    }

    public function fetchArray(PDOStatement $query): array
    {
        $out = [];
        while ($record = $query->fetch()) {
            $out[] = $record;
        }
        return $out;
    }

    public function fetch(string $query, array $params = []): mixed
    {
        $res = $this->query($query, $params);
        return $res->fetch();
    }

    public function insertId(): bool|string
    {
        return $this->sql->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->sql->beginTransaction();
    }

    public function commitTransaction(): bool
    {
        return $this->sql->commit();
    }

    public function rollbackTransaction(): bool
    {
        return $this->sql->rollBack();
    }

    /**
     * Helper function to insert a row into a table.
     */
    public function insertInto($table, $data, $dry = false): bool|PDOStatement|string
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
    public function updateRowQuery($fields): array
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

        $pp = (is_numeric($pp) && $pp > 0 ? (int) $pp : 20);
        $pp = min($pp, 100);

        return sprintf(" LIMIT %s, %s", (($page - 1) * $pp), $pp);
    }

    public function getServerVersion()
    {
        return $this->sql->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    private function logQueryForProfiler(string $query, array $params, float $startTime, float $executionTime): void
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

        // check if the caller isnt right here for queries done through fetch and fetchArray
        $caller = str_ends_with($backtrace[0]['file'] ?? '', 'Database.php')
            ? $backtrace[1] ?? []
            : $backtrace[0] ?? [];

        $this->queryLog[] = [
            'query'          => $query,
            'params'         => $params,
            'execution_time' => $executionTime,
            'timestamp'      => microtime(true),
            'caller_info'    => [
                'file'     => str_replace(SB_ROOT_PATH, '', $caller['file'] ?? 'unknown'), 
                'line'     => $caller['line'] ?? 'unknown',
                'function' => $caller['function'] ?? 'unknown',
            ],
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
        if (!$this->profilingEnabled || empty($this->queryLog)) {
            return [];
        }

        $totalTime = array_sum(array_column($this->queryLog, 'execution_time'));

        return [
            'total_queries' => count($this->queryLog),
            'total_time'    => $totalTime,
            'average_time'  => $totalTime / count($this->queryLog),
            'slowest_query' => array_reduce($this->queryLog, fn($c, $i) => 
                !$c || $i['execution_time'] > $c['execution_time'] ? $i : $c),
            'fastest_query' => array_reduce($this->queryLog, fn($c, $i) => 
                !$c || $i['execution_time'] < $c['execution_time'] ? $i : $c),
            'queries'       => array_map(fn($q) => [
                'query'       => $q['query'],
                'time'        => $q['execution_time'],
                'params'      => $q['params'],
                'caller_info' => $q['caller_info'],
            ], $this->queryLog),
        ];
    }
}
