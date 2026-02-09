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

namespace OpenSB;

use OpenSB\Database;
use OpenSB\UserCustomizationFont;

/**
 * class UserCustomizationData
 * 
 * Handles the user's Trinium profile customization data. 
 */
class UserCustomizationData
{
    /**
     * @var Database The database class
     */
    private Database $database;

    /**
     * @var mixed
     */
    private $data;

    /**
     * function __construct
     *
     * @param Database $database
     * @param mixed $id
     *
     * @return void
     */
    public function __construct(Database $database, $id)
    {
        $this->database = $database;

        $this->data = $this->database->fetch(
            "SELECT * FROM user_profile_customization WHERE user = ?",
            [$id]
        );

        // kind of stupid but at this point i dont care
        $font = UserCustomizationFont::tryFrom($this->data["font"]) ?? UserCustomizationFont::DEFAULT;
        $this->data["font"] = $font->getCss();
    }

    // i'm not sure if this should be array|false or ?array

    /**
     * function getData
     *
     * @return array|false
     */
    public function getData(): array|false
    {
        if (!$this->data) {
            return false;
        }

        $data = $this->data;

        $colorKeys = [
            'background_color',
            'title_color',
            'link_color',

            'basic_box_border_color',
            'basic_box_background_color',
            'basic_box_text_color',

            'highlight_box_border_color',
            'highlight_box_background_color',
            'highlight_box_text_color',
        ];

        foreach ($colorKeys as $key) {
            if (!empty($data[$key])) {
                $rgb = Utilities::hexToRgb($data[$key]);

                if ($rgb !== null) {
                    $data[$key . '_rgb'] = implode(' ', $rgb);
                }
            }
        }

        return $data;
    }
}
