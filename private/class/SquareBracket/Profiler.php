<?php

namespace SquareBracket;

/**
 * Revamped profiler.
 *
 * NOTE: The database query profiler is in the Database class.
 */
class Profiler
{
    private Database $database;
    private $starttime;
    private $database_query_log;
    private ?array $database_profiling_report;
    private bool $database_profiler_function_called = false;

    public function __construct($database) {
        $this->database = $database;
        $this->starttime = microtime(true);
    }

    // this should be called AFTER the database is done with everything
    private function getDatabaseProfilerInfo(): void
    {
        // slightly ugly hack so we dont repeat this shit (because of squarebrackettwigextension)
        if (!$this->database_profiler_function_called) {
            $this->database_profiler_function_called = true;
            $this->database_query_log = $this->database->getQueryLog();
            $this->database_profiling_report  = $this->database->getProfilingReport();
        }
    }

    private function whoAmI(): string
    {
        $whoami = exec('whoami');
        if ($whoami) {
            return "Running under system user " . $whoami;
        }
        return "Running under unknown system user";
    }

    public function getStats(): void
    {
        $this->getDatabaseProfilerInfo();

        printf("Rendered in %1.6fs with %dKB memory used and a total of %s database queries. %s.",
            microtime(true) - $this->starttime,
            memory_get_usage() / 1024,
            $this->database_profiling_report["total_queries"],
            $this->whoAmI());
    }

    public function getDatabaseProfilingReport(): ?array
    {
        $this->getDatabaseProfilerInfo();

        return $this->database_profiling_report;
    }
}