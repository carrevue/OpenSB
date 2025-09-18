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

namespace OpenSB\Tools;

define("BLUFF_ROOT_PATH", dirname(__DIR__));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once BLUFF_PRIVATE_PATH . '/common.php';

// migrate from opensb 1.2 table schema to opensb 2.0 table schema

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

// remove suggestions, which were a cut feature only see in early alpha 
// versions of opensb 1.0.
$database->query("DROP TABLE `suggestions`");
// update users table
$database->query("
ALTER TABLE `users`
CHANGE `lastview` `last_seen` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Timestamp of when they last logged in' AFTER `joined`,
CHANGE `featured_submission` `featured_upload` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `birthdate`,
CHANGE `customcolor` `userlink_color` varchar(7) COLLATE 'utf8mb4_general_ci' NULL DEFAULT '#0069B4' COMMENT 'The color that the user has set for their username' AFTER `about`,
CHANGE `u_flags` `flags` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '8 bools to determine certain user properties' AFTER `ip`,
DROP `profile_layout`,
DROP `language`,
DROP `group_id`;");

// update uploads table
$database->query("
ALTER TABLE `uploads`
CHANGE `video_id` `upload_id` varchar(11) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'Random alphanumeric upload ID which will be visible.' AFTER `id`,
CHANGE `title` `title` varchar(128) COLLATE 'utf8mb4_general_ci' NOT NULL COMMENT 'Upload title' AFTER `upload_id`,
CHANGE `description` `description` text COLLATE 'utf8mb4_general_ci' NULL COMMENT 'Upload description' AFTER `title`,
CHANGE `author` `author` bigint(20) unsigned NOT NULL COMMENT 'User ID of the upload author' AFTER `description`,
CHANGE `time` `timestamp` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Unix timestamp of when ' AFTER `author`,
DROP `most_recent_view`,
CHANGE `views` `views` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Upload views' AFTER `original_time`,
CHANGE `flags` `flags` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '8 bools to determine certain upload properties' AFTER `views`,
DROP `category_id`,
CHANGE `videofile` `upload_file` text COLLATE 'utf8mb4_general_ci' NULL COMMENT 'Upload file path' AFTER `flags`,
CHANGE `videolength` `video_length` bigint(20) unsigned NULL COMMENT 'Length of the video in seconds' AFTER `upload_file`,
CHANGE `tags` `tags` text COLLATE 'utf8mb4_general_ci' NULL COMMENT 'Upload tags, serialized in JSON' AFTER `video_length`,
CHANGE `post_type` `type` int(11) NOT NULL DEFAULT '0' COMMENT 'The upload type, 0 is a video, 1 is unused, 2 is image, and 3 is music.' AFTER `tags`;");

$database->query("ALTER TABLE `uploads`
CHANGE `original_time` `original_timestamp` bigint(20) unsigned NULL AFTER `original_site`;");

// update ipbans table
$database->query("ALTER TABLE `ip_bans`
CHANGE `time` `timestamp` bigint(20) NOT NULL DEFAULT '0' AFTER `reason`;");

// update journals table
$database->query("ALTER TABLE `journals`
CHANGE `date` `timestamp` int(11) NOT NULL AFTER `author`,
CHANGE `is_site_news` `is_news` tinyint(1) NOT NULL DEFAULT '0' AFTER `timestamp`;");

// update comments tables
$database->query("ALTER TABLE `journal_comments`
CHANGE `comment_id` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `id` `location_id` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `id`,
CHANGE `date` `timestamp` bigint(20) NOT NULL AFTER `author`,
DROP `deleted`;");
$database->query("ALTER TABLE `upload_comments`
CHANGE `comment_id` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `id` `location_id` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `id`,
CHANGE `date` `timestamp` bigint(20) NOT NULL AFTER `author`,
DROP `deleted`;");
$database->query("ALTER TABLE `user_profile_comments`
CHANGE `comment_id` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `id` `location_id` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `id`,
CHANGE `date` `timestamp` bigint(20) NOT NULL AFTER `author`,
DROP `deleted`;");

// update user_staff_notes table
$database->query("ALTER TABLE `user_staff_notes`
CHANGE `autoint` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `time` `timestamp` int(11) NOT NULL AFTER `author`;");

// update user_old_names table
$database->query("ALTER TABLE `user_old_names`
CHANGE `autoint` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `time` `timestamp` int(11) NOT NULL AFTER `old_name`;");

// update user_bans table
$database->query("ALTER TABLE `user_bans`
CHANGE `autoint` `id` int(11) NOT NULL AUTO_INCREMENT FIRST,
CHANGE `userid` `user` int(11) NOT NULL AFTER `id`,
CHANGE `time` `timestamp` bigint(20) NOT NULL DEFAULT '0' AFTER `reason`;");

// update upload tag meta table
$database->query("ALTER TABLE `upload_tag_meta`
CHANGE `latestUse` `last_usage` bigint(20) NOT NULL AFTER `name`;");

// update upload tag index table
$database->query("ALTER TABLE `upload_tag_index`
CHANGE `video_id` `upload_id` int NOT NULL FIRST;");

// update upload ratings table
$database->query("ALTER TABLE `upload_ratings`
CHANGE `video` `upload` bigint unsigned NOT NULL COMMENT 'Upload that is being rated.' AFTER `user`,
CHANGE `rating` `rating` tinyint(3) unsigned NOT NULL DEFAULT '1' AFTER `upload`;");

// update upload views table
$database->query("ALTER TABLE `upload_views`
CHANGE `video_id` `upload_id` text COLLATE 'utf8mb4_general_ci' NOT NULL FIRST;");

// update upload takedowns table
$database->query("ALTER TABLE `upload_takedowns`
CHANGE `submission` `upload` text COLLATE 'utf8mb4_general_ci' NOT NULL AFTER `id`;");
