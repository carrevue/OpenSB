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

// migrate from opensb 2.1 table schema to opensb 2.2 table schema

// alpha 1?

// private messaging 
// expands on the unfinished opensb 2.0 table
if (!$database->result("SHOW COLUMNS FROM `private_messages` LIKE 'read_date'")) {
    $database->query("ALTER TABLE `private_messages`
    CHANGE `id` `id` int(11) NOT NULL AUTO_INCREMENT,
    ADD `read_date` int(11) DEFAULT NULL,
    ADD `deleted_by_author` tinyint(1) NOT NULL DEFAULT 0,
    ADD `deleted_by_recipient` tinyint(1) NOT NULL DEFAULT 0,
    ADD PRIMARY KEY (`id`),
    ADD KEY `idx_recipient` (`recipient`, `deleted_by_recipient`, `date`),
    ADD KEY `idx_author` (`author`, `deleted_by_author`, `date`);");
}

if (!$database->result("SHOW INDEX FROM `private_messages` WHERE Key_name = 'idx_reply_to'")) {
    $database->query("ALTER TABLE `private_messages` ADD KEY `idx_reply_to` (`reply_to_id`);");
}