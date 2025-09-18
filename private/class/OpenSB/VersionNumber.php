<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

use BluffingoCore\GitInfo;
use Exception;

class VersionNumber
{
    private string $versionName;
    private string $versionNumber;
    private string $versionString;

    public function __construct()
    {
        $this->versionName = "Brightney";
        $this->versionNumber = "2.0.0-rc.1";
        $this->versionString = $this->makeVersionString();
    }

    /**
     * Makes the version string.
     */
    private function makeVersionString(): string
    {
        try {
            $gitInfo = new GitInfo();

            $branch = $gitInfo->getGitBranch();
            $hash = $gitInfo->getGitCommitHash();

            // if for example, the version number is opensb 2.0 and we're on 
            // the opensb-2.0 branch, we don't need to show the git branch as 
            // it would just repeat itself.
            if (preg_match('/^(\d+\.\d+)/', $this->versionNumber, $matches)) {
                $majorMinor = $matches[1];

                if (str_starts_with($branch, 'opensb-' . $majorMinor)) {
                    return sprintf('%s-%s', $this->versionNumber, $hash);
                }
            }

            return sprintf('%s.%s-%s', $this->versionNumber, $branch, $hash);
        } catch (Exception) {
            return $this->versionNumber;
        }
    }

    /**
     * Outputs the version banner.
     *
     * @return string
     */
    public function outputVersionBanner(): string
    {
        return sprintf("OpenSB %s %s - Executed on %s", $this->getVersionName(), $this->getVersionString(), date("Y-m-d h:i:s")) . PHP_EOL;
    }

    /**
     * Returns a version array intended for the frontend.
     *
     * @return array
     */
    public function getVersionArray(): array
    {
        return [
            "name" => $this->versionName,
            "number" => $this->versionNumber,
            "string" => $this->versionString,
        ];
    }

    /**
     * Returns the version name.
     *
     * @return string
     */
    public function getVersionName(): string
    {
        return $this->versionName;
    }

    /**
     * Returns the version number.
     *
     * @return string
     */
    public function getVersionNumber(): string
    {
        return $this->versionNumber;
    }

    /**
     * Returns the version string.
     *
     * @return string
     */
    public function getVersionString(): string
    {
        return $this->versionString;
    }
}
