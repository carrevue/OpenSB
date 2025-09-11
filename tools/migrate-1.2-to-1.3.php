#!/usr/bin/env php
<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2024-2025 Chaziz

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

define("BLUFF_ROOT_PATH", dirname(__DIR__));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once BLUFF_PRIVATE_PATH . '/common.php';

// migrate from opensb 1.2 table schema to opensb 1.3 table schema

$database->query("RENAME TABLE `bans` TO `user_bans`");
$database->query("DROP TABLE `blacklisted_referer`"); // unused table from early opensb 1.2
$database->query("RENAME TABLE `channel_comments` TO `user_profile_comments`");
$database->query("RENAME TABLE `comments` TO `upload_comments`");
$database->query("RENAME TABLE `deleted_videos` TO `upload_deleted`");
$database->query("RENAME TABLE `favorites` TO `user_favorites`");
// keep invite_keys
$database->query("RENAME TABLE `ipbans` TO `ip_bans`");
// keep journals
// keep journal_comments
$database->query("RENAME TABLE `notifications` TO `user_notifications`");
$database->query("RENAME TABLE `passwordresets` TO `user_password_resets`");
$database->query("RENAME TABLE `rating` TO `upload_ratings`");
$database->query("DROP TABLE `site_settings`"); // unused table from opensb 1.1 dev
$database->query("RENAME TABLE `subscriptions` TO `user_follows`");
$database->query("RENAME TABLE `tag_index` TO `upload_tag_index`");
$database->query("RENAME TABLE `tag_meta` TO `upload_tag_meta`");
$database->query("RENAME TABLE `takedowns` TO `upload_takedowns`");
// keep users
// keep user_old_names
// keep user_staff_notes
$database->query("RENAME TABLE `videos` TO `uploads`");
$database->query("RENAME TABLE `views` TO `upload_views`");

$database->query("CREATE TABLE `private_messages` (
  `id` int NOT NULL,
  `reply_to_id` int NULL,
  `title` varchar(128) NOT NULL,
  `contents` text NOT NULL,
  `author` int NOT NULL,
  `recipient` int NOT NULL,
  `date` int NOT NULL
);");

// NOTE: on prod, old profile custoization settings should be migrated from the pre-2023 sb db using
// cattledog. -chaziz 4/20/2025

$database->query("CREATE TABLE `user_profile_customization` (
  `user` int(11) NOT NULL,
  `font` text DEFAULT '',
  `background_color` varchar(7) NOT NULL DEFAULT '#FFFFFF',
  `title_color` varchar(7) NOT NULL DEFAULT '#333333',
  `link_color` varchar(7) NOT NULL DEFAULT '#0033cc',
  `basic_box_border_color` varchar(7) NOT NULL DEFAULT '#666666',
  `basic_box_background_color` varchar(7) NOT NULL DEFAULT '#FFFFFF',
  `basic_box_text_color` varchar(7) NOT NULL DEFAULT '#000000',
  `highlight_box_border_color` varchar(7) NOT NULL DEFAULT '#666666',
  `highlight_box_background_color` varchar(7) NOT NULL DEFAULT '#E6E6E6',
  `highlight_box_text_color` varchar(7) NOT NULL DEFAULT '#000000',
  PRIMARY KEY (`user`)
)");


// remove group_id and language from users table
$database->query("ALTER TABLE `users` DROP `group_id`");
$database->query("ALTER TABLE `users` DROP `language`");
