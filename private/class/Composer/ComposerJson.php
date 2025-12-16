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
 * Reads a composer.json file and provides accessors to get
 * its hash and the required dependencies
 *
 * @since BluffingoCore 1.0/MediaWiki 1.25
 */
class ComposerJson
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
     * Dependencies as specified by composer.json
     *
     * @return string[]
     */
    public function getRequiredDependencies()
    {
        $deps = [];
        if (isset($this->contents['require'])) {
            foreach ($this->contents['require'] as $package => $version) {
                // Examples of package dependencies that don't have a / in the name:
                // php, ext-xml, composer-plugin-api
                if (str_contains($package, '/')) {
                    $deps[$package] = self::normalizeVersion($version);
                }
            }
        }

        return $deps;
    }

    /**
     * Strip a leading "v" from the version name
     *
     * @param string $version
     * @return string
     */
    public static function normalizeVersion($version)
    {
        // Composer auto-strips the "v" in front of the tag name
        return ltrim($version, 'v');
    }
}
