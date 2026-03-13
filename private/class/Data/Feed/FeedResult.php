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

namespace Data\Feed;

use Core\Database;
use Data\Post\PostResult;
use Data\Upload\UploadResult;

class FeedResult
{
    public function __construct(
        private Database $database,
        private array $rows
    ) {}

    public function toCleanArray(): array
    {
        $out = [];

        $processors = [
            'upload' => UploadResult::class,
            'post'   => PostResult::class,
        ];

        foreach ($this->rows as $row) {
            $type = $row['item_type'];

            if (!isset($processors[$type])) continue;

            $item = (new $processors[$type]($this->database, [0 => $row]))->toCleanArray()[0];
            $item['item_type'] = $type;
            $out[] = $item;
        }

        return $out;
    }

    public function raw(): array
    {
        return $this->rows;
    }
}