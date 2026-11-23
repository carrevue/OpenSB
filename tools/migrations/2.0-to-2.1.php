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

// migrate from opensb 2.0 table schema to opensb 2.1 table schema

// beta 1
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


// beta 2
// asn bans (this is from opensb 2.1 beta 1.1 but Whatever)
$database->query("CREATE TABLE `asn_bans` (
  `asn` int(11) NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  `author` int(11) NOT NULL
);");

// add profile type (this is different from the one seen in early beta versions of 2.0)
$database->query("ALTER TABLE `user_profile_customization`
ADD `type` tinyint NOT NULL DEFAULT '0' AFTER `user`;");

// add indexed follower count
$database->query("ALTER TABLE `users`
ADD `f_index` bigint unsigned NOT NULL DEFAULT '0' AFTER `flags`;");

// add indexed upload count
$database->query("ALTER TABLE `users`
ADD `u_index` bigint unsigned NOT NULL DEFAULT '0' AFTER `f_index`;");

// add user/upload number history table
$database->query("CREATE TABLE user_number_history (
    user INT NOT NULL,
    date DATE NOT NULL,
    followers INT NOT NULL,
    uploads INT NOT NULL,
    PRIMARY KEY (user, date)
);");

$database->query("CREATE TABLE upload_number_history (
    upload INT NOT NULL,
    date DATE NOT NULL,
    views INT NOT NULL,
    views_raw INT NOT NULL,
    PRIMARY KEY (upload, date)
);");

// from after the inital release of 2.1.0 beta 2
$database->query("ALTER TABLE `user_number_history`
ADD `banned` tinyint(1) NOT NULL;");

$database->query("ALTER TABLE `upload_number_history`
CHANGE `upload` `upload` varchar(11) NOT NULL FIRST;");

// beta 3
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

// playlists
$database->query("CREATE TABLE `playlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `playlist_id` varchar(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `description` text DEFAULT NULL,
  `author` bigint(20) unsigned NOT NULL,
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `updated_timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  `upload_count` bigint(20) unsigned NOT NULL DEFAULT 0,
  `flags` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `visibility` int(11) NOT NULL DEFAULT 0,
  `thumbnail_upload` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_playlist_id` (`playlist_id`),
  KEY `idx_author` (`author`),
  KEY `idx_visibility` (`visibility`)
)");

$database->query("CREATE TABLE `playlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `playlist` bigint(20) unsigned NOT NULL,
  `upload` bigint(20) unsigned NOT NULL,
  `position` int(11) unsigned NOT NULL,
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_playlist_upload` (`playlist`,`upload`),
  KEY `idx_playlist_position` (`playlist`,`position`)
)");