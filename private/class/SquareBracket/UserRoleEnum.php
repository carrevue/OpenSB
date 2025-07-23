<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

namespace SquareBracket;

use InvalidArgumentException;

enum UserRoleEnum: int
{
    case None = 0;
    case Normal = 1;
    case Moderator = 2;
    case Administrator = 3;
    case Owner = 4;

    public static function fromString(string $rating): self
    {
        return match (strtolower($rating)) {
            'none' => self::None,
            'normal' => self::Normal,
            'moderator' => self::Moderator,
            'administrator' => self::Administrator,
            'owner' => self::Owner,
            default => throw new InvalidArgumentException("invalid role $rating")
        };
    }

    // for some reason the db uses sql enums and i forgot why i did it like this.
    public function toString(): string
    {
        return match ($this) {
            self::None => 'none',
            self::Normal => 'normal',
            self::Moderator => 'moderator',
            self::Administrator => 'administrator',
            self::Owner => 'owner'
        };
    }
}
