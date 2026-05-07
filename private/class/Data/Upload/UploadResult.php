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

namespace Data\Upload;

use Core\Database;
use Data\User\UserData;

class UploadResult
{
    public function __construct(
        private Database $database,
        private array $rows
    ) {}

    public function toCleanArray(): array
    {
        $out = [];

        foreach ($this->rows as $upload) {
            $flags = UploadFlags::toArray($upload["flags"]);

            $ratings = new UploadRatingData($this->database, $upload["id"]);

            $userData = new UserData($this->database, $upload["author"]);
            $out[] = [
                "id" => $upload["upload_id"],
                "title" => $upload["title"],
                "description" => $upload["description"],
                "published" => $upload["timestamp"],
                "published_originally" => $upload["original_timestamp"],
                "original_site" => $upload["original_site"],
                "type" => $upload["type"],
                "content_rating" => $upload["rating"],
                "views" => $upload["views"],
                "flags" => $flags,
                "length" => $upload["video_length"],
                "author" => [
                    "id" => $upload["author"],
                    "info" => $userData->getUserArray(),
                ],
                "interactions" => [
                    "ratings" => $ratings->calculateRatingData(),
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