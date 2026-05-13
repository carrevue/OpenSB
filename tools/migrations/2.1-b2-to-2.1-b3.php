#!/usr/bin/env php
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

namespace OpenSB\Tools;

define("SB_ROOT_PATH", dirname(__DIR__, 2));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once SB_PRIVATE_PATH . '/common.php';

// migrate from opensb 2.1 beta 2 table schema to opensb 2.1 beta 3 table schema

$database->query("CREATE TABLE `accounts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `email` varchar(254) NOT NULL,
  `password` varchar(128) NOT NULL,
  `token` varchar(128) NOT NULL,
  `registered` bigint(20) NOT NULL DEFAULT 0,
  `last_login` bigint(20) NOT NULL DEFAULT 0,
  `ip` varchar(48) NOT NULL DEFAULT '999.999.999.999',
  `birthdate` date NOT NULL,
  `flags` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
)");

$database->query("ALTER TABLE `users`
ADD `account_id` bigint NULL AFTER `id`;");