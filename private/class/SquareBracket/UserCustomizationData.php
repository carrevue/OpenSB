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

namespace SquareBracket;

use BluffingoCore\Database;
use SquareBracket\UserCustomizationFont;

class UserCustomizationData
{
    private Database $database;
    private $data;

    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->data = $this->database->fetch(
            "SELECT * FROM user_profile_customization WHERE user = ?",
            [$id]
        );

        // kind of stupid but at this point i dont care
        $font = UserCustomizationFont::tryFrom($this->data["font"]);
        $this->data["font"] = $font->getCss();
    }

    // i'm not sure if this should be array|false or ?array
    public function getData(): array|false
    {
        if ($this->data) {
            return $this->data;
        } else {
            return false;
        }
    }
}
