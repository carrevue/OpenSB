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

// migrate from opensb 2.0 table schema to opensb 2.1 beta 1 table schema

// TODO: migrate mature-rated uploads from rating to flag

// add visiblity type
$database->query("ALTER TABLE `uploads`
ADD `visibility` int(11) NOT NULL DEFAULT '0' AFTER `type`;");

// add username blocklist
$database->query("CREATE TABLE `username_blocklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `use_regex` tinyint(1) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `author` int(11) NOT NULL,
  PRIMARY KEY (`id`)
);");

// update ip bans
$database->query("ALTER TABLE `ip_bans`
CHANGE `reason` `reason` text COLLATE 'utf8mb4_general_ci' NOT NULL DEFAULT 'No reason specified' AFTER `ip`,
ADD `author` int NOT NULL DEFAULT '-1000';"); // -1000 is System

// mail verification token
$database->query("CREATE TABLE `email_verification_token` (
  `user` int NOT NULL,
  `token` varchar(128) NOT NULL,
  `created` int NOT NULL,
  `expiration` int NOT NULL,
  `last_sent` int NOT NULL,
  PRIMARY KEY (`token`)
);");