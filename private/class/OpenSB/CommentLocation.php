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

use InvalidArgumentException;

enum CommentLocation
{
    case Upload;
    case Profile;
    case Journal;

    public static function fromString(string $location): self
    {
        return match (strtolower(string: $location)) {
            'upload' => self::Upload,
            'profile' => self::Profile,
            'journal' => self::Journal,
            default => throw new InvalidArgumentException("invalid location $location")
        };
    }
}
