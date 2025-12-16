<?php

/*
  Copyright (C) 2014-2025 MediaWiki contributors

  MediaWiki is free software: you can redistribute it and/or modify it under
  the terms of the GNU General Public License as published by the Free Software
  Foundation, either version 2 of the License, or (at your option) any later 
  version.

  MediaWiki is distributed in the hope that it will be useful, but WITHOUT ANY 
  WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
  FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more 
  details.

  You should have received a copy of the GNU Affero General Public License
  along with this program.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace BluffingoCore\Composer;

/**
 * Reads a composer.lock file and provides accessors to get
 * its hash and what is installed
 *
 * @since BluffingoCore 1.0/MediaWiki 1.25
 */
class ComposerLock
{
    /**
     * @var array[]
     * @phan-var array{packages:array{name:string,version:string,type:string,license?:string,authors?:mixed,description?:string}}
     */
    private $contents;

    /**
     * @param string $location
     */
    public function __construct($location)
    {
        $this->contents = json_decode(file_get_contents($location), true);
    }

    /**
     * Dependencies currently installed according to composer.lock
     *
     * @return array[]
     */
    public function getInstalledDependencies()
    {
        $deps = [];
        foreach ($this->contents['packages'] as $installed) {
            $deps[$installed['name']] = [
                'version' => ComposerJson::normalizeVersion($installed['version']),
                'type' => $installed['type'],
                'licenses' => $installed['license'] ?? [],
                'authors' => $installed['authors'] ?? [],
                'description' => $installed['description'] ?? '',
            ];
        }

        return $deps;
    }
}
