<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

namespace Data\UserProfile;

enum CustomizationFontEnum: string
{
    case DEFAULT = 'default';
    case ARIAL = 'arial';
    case COMIC_SANS = 'comic_sans';
    case TIMES_NEW_ROMAN = 'times_new_roman';
    case TAHOMA = 'tahoma';
    case VERDANA = 'verdana';

    public function getName(): string
    {
        return match ($this) {
            self::DEFAULT => 'Default',
            self::ARIAL => 'Arial/Helvetica',
            self::COMIC_SANS => 'Comic Sans MS',
            self::TIMES_NEW_ROMAN => 'Times New Roman',
            self::TAHOMA => 'Tahoma',
            self::VERDANA => 'Verdana',
        };
    }

    public function getCss(): string
    {
        return match ($this) {
            self::DEFAULT => '',
            self::ARIAL => '"Helvetica Neue", Helvetica, Arial, sans-serif',
            self::COMIC_SANS => '"Comic Sans MS", "Comic Sans", cursive',
            self::TIMES_NEW_ROMAN => '"Times New Roman", Times, Georgia, serif',
            self::TAHOMA => 'Tahoma, Verdana, sans-serif',
            self::VERDANA => 'Verdana, Tahoma, sans-serif',
        };
    }

    public static function getAll(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = [
                'name' => $case->getName(),
                'css' => $case->getCss()
            ];
        }
        return $result;
    }
}
