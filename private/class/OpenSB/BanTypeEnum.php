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

enum BanTypeEnum: int
{
    case Unknown = 0;
    case Underaged = 1;
    case Impersonation = 2;
    case CopyrightInfringement = 3;
    case UploadCommunityGuidelines = 4;
    case ProblematicIndividual = 5;
    case ProblematicAssociation = 6;

    /**
     * function toString
     *
     * @return string
     */
    public function toString(): string
    {
        return match ($this) {
            self::Unknown => 'none',
            self::Underaged => 'normal',
            self::Impersonation => 'moderator',
            self::CopyrightInfringement => 'administrator',
            self::UploadCommunityGuidelines => 'owner',
            self::ProblematicIndividual => 'owner',
            self::ProblematicAssociation => 'owner',
        };
    }
}
