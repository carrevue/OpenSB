<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

class GitInfo
{
    private bool $isSubmodule;
    private string $gitCommitHash;
    private string $gitBranch;
    private string $gitPath;

    public function __construct($path = null)
    {
        $this->gitBranch = "";
        $this->gitCommitHash = "";

        if (isset($path) && file_exists($path)) {
            $this->gitPath = $path;
            $this->isSubmodule = true;
        } elseif (file_exists(SB_GIT_PATH)) {
            $this->gitPath = SB_GIT_PATH;
            $this->isSubmodule = false;
        } else {
            throw new Exception("The Git path does not exist.");
        }

        if ($this->isSubmodule) {
            $gitFile = $this->gitPath . '/.git';

            $content = file_get_contents($gitFile);

            if (preg_match('/^gitdir: (.*)$/m', $content, $matches)) {
                $gitDir = trim($matches[1]);
                // handle relative paths
                if ($gitDir[0] !== '/') {
                    $gitDir = $this->gitPath . '/' . $gitDir;
                }

                $gitHeadLocation = $gitDir . '/HEAD';
                $gitHead = file_get_contents($gitHeadLocation);

                // if detached head
                if (preg_match('/^[0-9a-f]{40}$/', trim($gitHead))) {
                    $this->gitCommitHash = substr(trim($gitHead), 0, 7);
                }

                // if on a branch
                if (preg_match('/ref: refs\/heads\/(.*)$/', $gitHead, $matches)) {
                    $this->gitBranch = trim($matches[1]);
                    $commitFile = $gitDir . '/refs/heads/' . $this->gitBranch;

                    if (file_exists($commitFile)) {
                        $commit = file_get_contents($commitFile); // kind of bad but hey it works
                        $this->gitCommitHash = substr(trim($commit), 0, 7);
                    }
                }
            }
        } else {
            $gitHead = file_get_contents(SB_GIT_PATH . '/HEAD');
            $this->gitBranch = rtrim(preg_replace("/(.*?\/){2}/", '', $gitHead));
            $commit = file_get_contents(SB_GIT_PATH . '/refs/heads/' . $this->gitBranch); // kind of bad but hey it works

            $this->gitCommitHash = substr($commit, 0, 7);
        }
    }

    public function getGitBranch()
    {
        return $this->gitBranch;
    }

    public function getGitCommitHash()
    {
        return $this->gitCommitHash;
    }
}
