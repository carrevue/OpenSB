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

namespace OpenSB;

use OpenSB\PostTypeEnum;

class PostResult
{
    public function __construct(
        private Database $database,
        private array $rows
    ) {}

    public function toCleanArray(): array
    {
        $out = [];

        foreach ($this->rows as $post) {
            $userData = new UserData($this->database, $post['author']);

            $out[] = [
                'type'       => PostTypeEnum::from($post['type'])->toString(),
                'id'         => $post['id'],
                'contents'   => $post['contents'],
                'published'  => $post['timestamp'],
                'attachment' => $post['attachment'],
                'author'     => [
                    'id'   => $post['author'],
                    'info' => $userData->getUserArray(),
                ],
            ];
        }

        return $out;
    }

    public function raw(): array
    {
        return $this->rows;
    }
}