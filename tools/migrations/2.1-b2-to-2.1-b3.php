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


// TODO: use foreign keys for every table, also fix inconsistent ints for users. -chaziz 05/15/2026
$database->query("CREATE TABLE `account_user_roles` (
  `account` bigint(20) NOT NULL,
  `user` int(11) NOT NULL,
  `role` tinyint(4) NOT NULL,
  UNIQUE KEY `unique_account_user` (`account`,`user`),
  KEY `idx_user` (`user`),
  CONSTRAINT `1` FOREIGN KEY (`account`) REFERENCES `accounts` (`id`),
  CONSTRAINT `2` FOREIGN KEY (`user`) REFERENCES `users` (`id`)
)");

// btw for any of the bluds reading this for their fuckass site
// you would be better off using peertube than to try adding
// activitypub support on opensb. i tried it 2 years ago and the
// protocol sucks ass. opensb is not built for this and you'll end
// up with a horrifying unoptimized pile of shit. -chaziz 05/15/2026

