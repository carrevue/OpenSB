<?php

/*
  Copyright (C) 2015-2025 MediaWiki contributors

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
 * Reads an installed.json file and provides accessors to get what is
 * installed
 *
 * @since BluffingoCore 1.0/MediaWiki 1.27
 */
class ComposerInstalled
{
    /**
     * @var array[]
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
     * Dependencies currently installed according to installed.json
     *
     * @return array[]
     */
    public function getInstalledDependencies()
    {
        $contents = $this->contents['packages'];

        $deps = [];
        foreach ($contents as $installed) {
            $deps[$installed['name']] = [
                'version' => ComposerJson::normalizeVersion($installed['version']),
                'type' => $installed['type'],
                'licenses' => $installed['license'] ?? [],
                'authors' => $installed['authors'] ?? [],
                'description' => $installed['description'] ?? '',
            ];
        }

        ksort($deps);
        return $deps;
    }
}
