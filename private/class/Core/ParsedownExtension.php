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

namespace Core;

use Parsedown;

/**
 * class ParsedownExtension
 * 
 * This Parsedown extension is used by SquareBracketTwigExtension's 
 * markdown_user_written function to remove the ability for users to
 * add headers to their comments.
 */
class ParsedownExtension extends Parsedown
{
    /**
     * function blockHeader
     * 
     * Disables headers.
     *
     * @param mixed $Line
     *
     * @return void
     */
    protected function blockHeader($Line): void {}

    /**
     * function blockSetextHeader
     * 
     * Disables headers.
     *
     * @param mixed $Line
     * @param mixed $Block
     *
     * @return void
     */
    protected function blockSetextHeader($Line, array|null $Block = []): void {}
}
