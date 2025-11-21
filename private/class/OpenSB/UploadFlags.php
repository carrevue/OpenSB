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

enum UploadFlags: int
{
    /**
     * 00000001: Featured upload
     */
    case FLAG_FEATURED = 1;

    /**
     * 00000010: Unprocessed video upload
     */
    case FLAG_UNPROCESSED = 2;

    /**
     * 00000100: "Block guests from viewing this upload"
     */
    case FLAG_BLOCK_GUESTS = 4;

    /**
     * 00001000: "Disable comments on this upload"
     */
    case FLAG_BLOCK_COMMENTS = 8;

    /**
     * 00010000: Upload has custom thumbnail
     */
    case FLAG_CUSTOM_THUMBNAIL = 16;

    public static function toArray(int $flags): array
    {
        return [
            'featured' => (bool)($flags & self::FLAG_FEATURED->value),
            'unprocessed' => (bool)($flags & self::FLAG_UNPROCESSED->value),
            'block_guests' => (bool)($flags & self::FLAG_BLOCK_GUESTS->value),
            'block_comments' => (bool)($flags & self::FLAG_BLOCK_COMMENTS->value),
            'custom_thumbnail' => (bool)($flags & self::FLAG_CUSTOM_THUMBNAIL->value),
        ];
    }
}
