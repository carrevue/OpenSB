-- Adminer 5.4.1 MariaDB 11.8.3-MariaDB-0+deb13u1 from Debian dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

SET NAMES utf8mb4;

DROP TABLE IF EXISTS `email_verification_token`;
CREATE TABLE `email_verification_token` (
  `user` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `created` int(11) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


DROP TABLE IF EXISTS `invite_keys`;
CREATE TABLE `invite_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invite_key` varchar(64) NOT NULL,
  `generated_by` int(11) NOT NULL,
  `claimed_by` int(11) DEFAULT NULL,
  `generated_time` int(11) NOT NULL,
  `claimed_time` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `ip_bans`;
CREATE TABLE `ip_bans` (
  `ip` varchar(45) NOT NULL DEFAULT '0.0.0.0',
  `reason` text NOT NULL DEFAULT 'No reason specified',
  `timestamp` bigint(20) NOT NULL DEFAULT 0,
  `author` int(11) NOT NULL DEFAULT -1000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `journals`;
CREATE TABLE `journals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(128) NOT NULL,
  `post` text NOT NULL,
  `author` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `is_news` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `journal_comments`;
CREATE TABLE `journal_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` text NOT NULL,
  `reply_to` bigint(20) NOT NULL DEFAULT 0,
  `comment` text NOT NULL,
  `author` bigint(20) NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `private_messages`;
CREATE TABLE `private_messages` (
  `id` int(11) NOT NULL,
  `reply_to_id` int(11) DEFAULT NULL,
  `title` varchar(128) NOT NULL,
  `contents` text NOT NULL,
  `author` int(11) NOT NULL,
  `recipient` int(11) NOT NULL,
  `date` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


DROP TABLE IF EXISTS `uploads`;
CREATE TABLE `uploads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Incrementing ID for internal purposes.',
  `upload_id` varchar(11) NOT NULL COMMENT 'Random alphanumeric upload ID which will be visible.',
  `title` varchar(128) NOT NULL COMMENT 'Upload title',
  `description` text DEFAULT NULL COMMENT 'Upload description',
  `author` bigint(20) unsigned NOT NULL COMMENT 'User ID of the upload author',
  `timestamp` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Unix timestamp of when ',
  `original_site` varchar(64) DEFAULT NULL,
  `original_timestamp` bigint(20) unsigned DEFAULT NULL,
  `views` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Upload views',
  `flags` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '8 bools to determine certain upload properties',
  `upload_file` text DEFAULT NULL COMMENT 'Upload file path',
  `video_length` bigint(20) unsigned DEFAULT NULL COMMENT 'Length of the video in seconds',
  `tags` text DEFAULT NULL COMMENT 'Upload tags, serialized in JSON',
  `type` int(11) NOT NULL DEFAULT 0 COMMENT 'The upload type, 0 is a video, 1 is a legacy video, 2 is art, and 3 is music.',
  `visibility` int(11) NOT NULL DEFAULT 0,
  `rating` enum('general','questionable','mature') NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_comments`;
CREATE TABLE `upload_comments` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `location_id` text NOT NULL COMMENT 'ID to video or user.',
  `reply_to` bigint(20) NOT NULL DEFAULT 0,
  `comment` text NOT NULL COMMENT 'The comment itself, formatted in Markdown.',
  `author` bigint(20) NOT NULL COMMENT 'Numerical ID of comment author.',
  `timestamp` bigint(20) NOT NULL COMMENT 'UNIX timestamp when the comment was posted.',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_deleted`;
CREATE TABLE `upload_deleted` (
  `autoint` int(11) NOT NULL AUTO_INCREMENT,
  `id` varchar(11) NOT NULL,
  `uploaded_time` bigint(20) NOT NULL,
  `deleted_time` bigint(20) NOT NULL,
  PRIMARY KEY (`autoint`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_ratings`;
CREATE TABLE `upload_ratings` (
  `user` bigint(20) unsigned NOT NULL COMMENT 'User that does the rating.',
  `upload` bigint(20) unsigned NOT NULL COMMENT 'Upload that is being rated.',
  `rating` tinyint(3) unsigned NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_tag_index`;
CREATE TABLE `upload_tag_index` (
  `upload_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_tag_meta`;
CREATE TABLE `upload_tag_meta` (
  `tag_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `last_usage` bigint(20) NOT NULL,
  PRIMARY KEY (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_takedowns`;
CREATE TABLE `upload_takedowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `upload` text NOT NULL,
  `time` int(11) NOT NULL,
  `reason` text NOT NULL,
  `sender` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `upload_views`;
CREATE TABLE `upload_views` (
  `upload_id` text NOT NULL,
  `user` text NOT NULL,
  `timestamp` int(11) NOT NULL,
  `type` enum('guest','user') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `username_blocklist`;
CREATE TABLE `username_blocklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `use_regex` tinyint(1) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `author` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Incrementing ID for internal purposes.',
  `name` varchar(128) NOT NULL COMMENT 'Username, chosen by the user',
  `email` varchar(128) NOT NULL,
  `password` varchar(128) NOT NULL COMMENT 'Password, hashed in bcrypt.',
  `admin_password` varchar(128) DEFAULT NULL,
  `token` varchar(128) NOT NULL COMMENT 'User token for cookie authentication.',
  `joined` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'User''s join timestamp',
  `last_seen` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT 'Timestamp of when they last logged in',
  `birthdate` date DEFAULT NULL,
  `featured_upload` bigint(20) unsigned NOT NULL DEFAULT 0,
  `title` text NOT NULL COMMENT 'Display Name',
  `about` text DEFAULT NULL COMMENT 'User''s description',
  `userlink_color` varchar(7) DEFAULT '#0069B4' COMMENT 'The color that the user has set for their profile',
  `avatar` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(48) DEFAULT '999.999.999.999',
  `flags` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '8 bools to determine certain user properties',
  `powerlevel` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '0 - banned. 1 - normal user. 2 - moderator. 3 - administrator',
  `comfortable_rating` enum('general','questionable','mature') NOT NULL,
  `blacklisted_tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`blacklisted_tags`)),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_bans`;
CREATE TABLE `user_bans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `reason` text NOT NULL,
  `timestamp` bigint(20) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_favorites`;
CREATE TABLE `user_favorites` (
  `user_id` int(11) NOT NULL,
  `video_id` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_follows`;
CREATE TABLE `user_follows` (
  `id` int(11) NOT NULL COMMENT 'ID of the user that wants to subscribe to a user.',
  `user` int(11) NOT NULL COMMENT 'The user that the user wants to subscribe to.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` int(11) NOT NULL,
  `level` int(11) DEFAULT NULL,
  `recipient` int(11) NOT NULL,
  `sender` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `related_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_old_names`;
CREATE TABLE `user_old_names` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `old_name` varchar(128) NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_password_resets`;
CREATE TABLE `user_password_resets` (
  `id` varchar(64) NOT NULL,
  `user` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_profile_comments`;
CREATE TABLE `user_profile_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `location_id` text NOT NULL,
  `reply_to` bigint(20) NOT NULL DEFAULT 0,
  `comment` text NOT NULL,
  `author` bigint(20) NOT NULL,
  `timestamp` bigint(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `user_profile_customization`;
CREATE TABLE `user_profile_customization` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;


DROP TABLE IF EXISTS `user_staff_notes`;
CREATE TABLE `user_staff_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` int(11) NOT NULL,
  `note` text NOT NULL,
  `author` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 2026-02-27 16:28:36 UTC