<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

use RuntimeException;
use stdClass;

class SkinInfo
{
    private static array $cache = [];

    private array $info = [];

    public function __construct(string $skin)
    {
        $path = SB_PRIVATE_PATH . '/skins/' . $skin;

        if (isset(self::$cache[$skin])) {
            $this->info = self::$cache[$skin];
            return;
        }

        if (file_exists($path . "/skin.json")) {
            $metadata = file_get_contents($path . "/skin.json");

            if ($metadata === false) {
                throw new RuntimeException(sprintf("Could not read the metadata for skin %s", $skin));
            }

            $metadata = json_decode($metadata, true);

            // awkward leftover from the opensb 1.2 beta era, when there was
            // gonna be a secondary site to sb called "soos" which died in
            // very early development.
            if (isset($metadata->site) && $metadata->site !== "squarebracket") {
                throw new RuntimeException(sprintf("%s is incompatible", $skin));
            }

            // anti-stupid check
            if (isset($metadata->requires->MediaWiki)) {
                throw new RuntimeException(sprintf("%s is incompatible", $skin));
            }

            /*
            // version check (very crude for now)
            if (!isset($metadata["version"])) {
                throw new RuntimeException(sprintf("%s is incompatible", $skin));
            }
            */

            $this->info = $metadata;
            self::$cache[$skin] = $metadata;
        }
    }

    public function getInfo() {
        return $this->info;
    }
}