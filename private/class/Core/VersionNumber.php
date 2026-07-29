<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

/**
 * class VersionNumber
 * 
 * This class generates OpenSB's version number.
 * 
 * @since OpenSB 1.1
 */
class VersionNumber
{
    /**
     * @var string The version name.
     */
    private static string $versionName = "Marigold";

    /**
     * @var string The version number, which tries to follow Semantic versioning.
     */
    private static string $versionNumber = "2.2.0-dev";

    /**
     * @var string The current Git branch.
     */
    private static string $branch;
    /**
     * @var string The current Git commit hash.
     */
    private static string $hash;

    /**
     * @var string The full complete version string.
     */
    private static string $versionString;

    /**
     * function __construct
     *
     * @return void
     */
    public function __construct()
    {
        try {
            $gitInfo = new GitInfo();
            self::$branch = $gitInfo->getGitBranch();
            self::$hash = $gitInfo->getGitCommitHash();

            self::$versionString = $this->makeVersionString();
        } catch (Exception) {
            self::$branch = "unknown";
            self::$hash = "unknown";

            self::$versionString = self::$versionNumber;
        }
    }

    /**
     * function makeVersionString
     *
     * Makes the version string.
     *
     * @return string
     */
    private function makeVersionString(): string
    {
        if (preg_match('/^(\d+\.\d+)/', self::$versionNumber, $matches)) {
            $majorMinor = $matches[1];

            if (str_starts_with(self::$branch, 'opensb-' . $majorMinor)) {
                return sprintf('%s-%s', self::$versionNumber, self::$hash);
            }
        } else {
            return sprintf('%s.%s-%s', self::$versionNumber, self::$branch, self::$hash);
        }
    }

    /**
     * function outputVersionBanner
     *
     * Outputs the version banner, typically used in logs.
     *
     * @return string
     */
    public function outputVersionBanner(): string
    {
        return sprintf("OpenSB %s %s - Executed on %s", $this->getVersionName(), $this->getVersionString(), date("Y-m-d h:i:s")) . PHP_EOL;
    }

    /**
     * function getVersionArray
     *
     * Returns a version array intended for the skin.
     *
     * @return array
     */
    public function getVersionArray(): array
    {
        return [
            "name" => self::$versionName,
            "number" => self::$versionNumber,
            "string" => self::$versionString,
            "hash" => self::$hash,
        ];
    }

    /**
     * function getVersionName
     *
     * Returns the version name.
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return self::$versionName;
    }

    /**
     * function getVersionNumber
     *
     * Returns the version number.
     *
     * @return string
     */
    public function getVersionNumber(): string
    {
        return self::$versionNumber;
    }

    /**
     * function getVersionString
     *
     * Returns the version string.
     *
     * @return string
     */
    public function getVersionString(): string
    {
        return self::$versionString;
    }
}
