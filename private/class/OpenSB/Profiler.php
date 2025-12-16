<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou
  Copyright (C) 2021-2025 Chaziz

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

    public function __construct($database)
    {
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

        printf(
            "Rendered in %1.6fs with %dKB memory used. %s.",
            microtime(true) - $this->starttime,
            memory_get_usage() / 1024,
            $this->whoAmI()
        );
    }

    public function getDatabaseProfilingReport(): ?array
    {
        $this->getDatabaseProfilerInfo();

        return $this->database_profiling_report;
    }
}
