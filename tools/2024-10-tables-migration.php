#!/usr/bin/env php
<?php
namespace OpenSB;

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_DYNAMIC_PATH", SB_ROOT_PATH . '/dynamic');
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once SB_PRIVATE_PATH . '/common.php';

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

// profile layout (TODO: this should probably be removed)
$database->query("ALTER TABLE `users` ADD `profile_layout` TINYINT NOT NULL AFTER `customcolor`");

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

// TODO: replace the default color palette (which wont be normally shown on the site but still)
$database->query("CREATE TABLE `user_profile_customization` (
  `user` int(11) NOT NULL,
  `background` varchar(7) NOT NULL DEFAULT '#ffffff',
  `fontcolor` varchar(7) NOT NULL DEFAULT '#222222',
  `titlefont` varchar(7) NOT NULL DEFAULT '#ffffff',
  `link` varchar(7) NOT NULL DEFAULT '#0033CC',
  `headerfont` varchar(7) NOT NULL DEFAULT '#ffffff',
  `highlightheader` varchar(7) NOT NULL DEFAULT '#3399cc',
  `highlightinside` varchar(7) NOT NULL DEFAULT '#ecf4fb',
  `regularheader` varchar(7) NOT NULL DEFAULT '#3399cc',
  `regularinside` varchar(7) NOT NULL DEFAULT '#ffffff',
  PRIMARY KEY (`user`)
)");