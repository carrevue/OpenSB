<?php

namespace SquareBracket;

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
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="TRADITIONAL"'
        ];

        $this->sql = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, $options);
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

    public function result($query, $params = [])
    {
        $res = $this->query($query, $params);
        return $res->fetchColumn();
    }

    public function query($query, $params = [])
    {
        $startTime = microtime(true);

        $res = $this->sql->prepare($query);
        $res->execute($params);

        $executionTime = microtime(true) - $startTime;

        if ($this->profilingEnabled) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $immediateCaller = $backtrace[0] ?? [];
            $actualCaller = $backtrace[1] ?? [];

            // check if the caller isnt right here for queries done through fetch and fetchArray
            $caller = (str_ends_with($immediateCaller['file'] ?? '', 'Database.php'))
                ? $actualCaller
                : $immediateCaller;

            // remove root path so we have a shorter string
            $file = str_replace(SB_ROOT_PATH, '', $caller['file'] ?? '');

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

    public function getVersion()
    {
        return $this->sql->getAttribute(PDO::ATTR_SERVER_VERSION);
    }
}