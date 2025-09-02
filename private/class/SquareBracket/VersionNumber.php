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

namespace SquareBracket;

class VersionNumber
{
    private string $versionNumber;
    private string $versionString;

    public function __construct()
    {
        $this->versionNumber = "1.3.0-beta.11-preview";
        $this->versionString = $this->makeVersionString();
    }

    /**
     * Makes the version string.
     *
     */
    private function makeVersionString(): string
    {
        if (file_exists(BLUFF_GIT_PATH)) {
            $gitHead = file_get_contents(BLUFF_GIT_PATH . '/HEAD');
            $gitBranch = rtrim(preg_replace("/(.*?\/){2}/", '', $gitHead));
            $commit = file_get_contents(BLUFF_GIT_PATH . '/refs/heads/' . $gitBranch); // kind of bad but hey it works

            $hash = substr($commit, 0, 7);

            // if for example, the version number is opensb 1.3 and we're on 
            // the opensb-1.3 branch, we don't need to show the git branch as 
            // it would just repeat itself.
            if (preg_match('/^(\d+\.\d+)/', $this->versionNumber, $matches)) {
                $majorMinor = $matches[1];

                if (str_starts_with($gitBranch, 'opensb-' . $majorMinor)) {
                    return sprintf('%s-%s', $this->versionNumber, $hash);
                }
            }

            return sprintf('%s.%s-%s', $this->versionNumber, $gitBranch, $hash);
        } else {
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
        return sprintf("OpenSB %s - Executed on %s", $this->getVersionString(), date("Y-m-d h:i:s")) . PHP_EOL;
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
