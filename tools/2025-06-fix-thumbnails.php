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

namespace OpenSB\Tools;

// INCOMPLETE

define("BLUFF_ROOT_PATH", dirname(__DIR__));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once BLUFF_PRIVATE_PATH . '/common.php';

// TODO
$uploads = $database->fetchArray($database->query("SELECT * FROM uploads"));
