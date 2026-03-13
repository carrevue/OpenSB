<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Data\User;

enum UserFlags: int
{
    /**
     * 00000001: Enable profile customization
     */
    case FLAG_PROFILE_CUSTOMIZATION_ENABLED = 1;

    /**
     * 00000010: Unverified user
     * 
     * @note In earlier versions of OpenSB, this was only used for 
     * squareBracket/FulpTube's manual Discord verification. Since
     * OpenSB 2.1, this has changed to be used for email verification.
     */
    case FLAG_UNVERIFIED = 2;

    /**
     * 00000100: Featured user (shown on guide/list to logged out users)
     */
    case FLAG_FEATURED = 4;

    /**
     * 00001000: Enable access to mature content
     */
    case FLAG_MATURE_CONTENT_ACCESS = 8;

    /**
     * 00010000: Hide from recommendations
     */
    case FLAG_SHADOW_BAN = 16;
    
    /**
     * 01000000: Account was created on FulpTube.rocks
     */
    case FLAG_FULPTUBE_ACCOUNT = 64;

    /**
     * 10000000: Account has access to the QA instance
     */
    case FLAG_QA_ACCESS = 128;

    /**
     * function toArray
     * 
     * Returns the user flag array
     *
     * @param int $flags
     *
     * @return array
     */
    public static function toArray(int $flags): array
    {
        return [
            'fulptube_account' => (bool)($flags & self::FLAG_FULPTUBE_ACCOUNT->value),
            'qa_access' => (bool)($flags & self::FLAG_QA_ACCESS->value),
            'unverified' => (bool)($flags & self::FLAG_UNVERIFIED->value),
            'featured' => (bool)($flags & self::FLAG_FEATURED->value),
            'mature_content_access' => (bool)($flags & self::FLAG_MATURE_CONTENT_ACCESS->value),
            'profile_customization_enabled' => (bool)($flags & self::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value),
        ];
    }
}
