<?php
namespace OpenSB;

// TODO: make this work with new opensb 1.3 table names -chaziz 1/6/2025

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_DYNAMIC_PATH", SB_ROOT_PATH . '/dynamic');
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

global $database;

require_once SB_PRIVATE_PATH . '/common.php';

$users = $database->fetchArray($database->query("SELECT id, name, joined FROM users ORDER BY joined ASC"));

$new_id = 0;
$id_mapping = [];
$temp_id_base = 1000000; // avoid stupid conflicting shit

// internally update all user ids to be in an order more akin to join date.
// squarebracket's prod db has a gap between id205 and id1654 due to a botting incident from around june 2023
// this fixes that. poktube accs will however be in the wrong order due to poktube's db originally only storing
// dates as YYYY-MM-DD.
// i did however have to update the db in preparation for this script:
// * chaziz and squarebracket's join dates have been modified, so they stay as account #1 and #2
// * the old qobo and poktube accounts were merged into the squarebracket account.
// * renamed "dummy" accounts (for example, dummyID12 to DummyAccount-2022-04-07).
// -chaziz 6/19/2024
foreach ($users as $user) {
    $new_id++;
    $temp_id = $temp_id_base + $new_id;
    $id_mapping[$user["id"]] = $temp_id;
    echo "{$user['id']} to $temp_id\n";
}

var_dump($id_mapping);

foreach ($id_mapping as $old_id => $temp_id) {
    $database->query("UPDATE bans SET userid = ? WHERE userid = ?", [$temp_id, $old_id]);
    $database->query("UPDATE channel_comments SET id = ? WHERE id = ?", [$temp_id, $old_id]);
    $database->query("UPDATE channel_comments SET author = ? WHERE author = ?", [$temp_id, $old_id]);
    $database->query("UPDATE comments SET author = ? WHERE author = ?", [$temp_id, $old_id]);
    $database->query("UPDATE favorites SET user_id = ? WHERE user_id = ?", [$temp_id, $old_id]);
    $database->query("UPDATE invite_keys SET generated_by = ? WHERE generated_by = ?", [$temp_id, $old_id]);
    $database->query("UPDATE invite_keys SET claimed_by = ? WHERE claimed_by = ?", [$temp_id, $old_id]);
    $database->query("UPDATE journals SET author = ? WHERE author = ?", [$temp_id, $old_id]);
    $database->query("UPDATE journal_comments SET author = ? WHERE author = ?", [$temp_id, $old_id]);
    $database->query("UPDATE notifications SET recipient = ? WHERE recipient = ?", [$temp_id, $old_id]);
    $database->query("UPDATE notifications SET sender = ? WHERE sender = ?", [$temp_id, $old_id]);
    $database->query("UPDATE rating SET user = ? WHERE user = ?", [$temp_id, $old_id]);
    $database->query("UPDATE subscriptions SET id = ? WHERE id = ?", [$temp_id, $old_id]);
    $database->query("UPDATE subscriptions SET user = ? WHERE user = ?", [$temp_id, $old_id]);
    $database->query("UPDATE takedowns SET sender = ? WHERE sender = ?", [$temp_id, $old_id]);
    $database->query("UPDATE users SET new_id = ? WHERE id = ?", [$temp_id, $old_id]);
    $database->query("UPDATE user_old_names SET user = ? WHERE user = ?", [$temp_id, $old_id]);
    $database->query("UPDATE videos SET author = ? WHERE author = ?", [$temp_id, $old_id]);
}

foreach ($id_mapping as $old_id => $temp_id) {
    $new_id = $temp_id - $temp_id_base;
    $user = $database->fetchArray($database->query("SELECT name FROM users WHERE new_id = ?", [$temp_id]))[0];
    $database->query("UPDATE bans SET userid = ? WHERE userid = ?", [$new_id, $temp_id]);
    $database->query("UPDATE channel_comments SET id = ? WHERE id = ?", [$new_id, $temp_id]);
    $database->query("UPDATE channel_comments SET author = ? WHERE author = ?", [$new_id, $temp_id]);
    $database->query("UPDATE comments SET author = ? WHERE author = ?", [$new_id, $temp_id]);
    $database->query("UPDATE favorites SET user_id = ? WHERE user_id = ?", [$new_id, $temp_id]);
    $database->query("UPDATE invite_keys SET generated_by = ? WHERE generated_by = ?", [$new_id, $temp_id]);
    $database->query("UPDATE invite_keys SET claimed_by = ? WHERE claimed_by = ?", [$new_id, $temp_id]);
    $database->query("UPDATE journals SET author = ? WHERE author = ?", [$new_id, $temp_id]);
    $database->query("UPDATE journal_comments SET author = ? WHERE author = ?", [$new_id, $temp_id]);
    $database->query("UPDATE notifications SET recipient = ? WHERE recipient = ?", [$new_id, $temp_id]);
    $database->query("UPDATE notifications SET sender = ? WHERE sender = ?", [$new_id, $temp_id]);
    $database->query("UPDATE rating SET user = ? WHERE user = ?", [$new_id, $temp_id]);
    $database->query("UPDATE subscriptions SET id = ? WHERE id = ?", [$new_id, $temp_id]);
    $database->query("UPDATE subscriptions SET user = ? WHERE user = ?", [$new_id, $temp_id]);
    $database->query("UPDATE takedowns SET sender = ? WHERE sender = ?", [$new_id, $temp_id]);
    $database->query("UPDATE users SET new_id = ? WHERE new_id = ?", [$new_id, $temp_id]);
    $database->query("UPDATE user_old_names SET user = ? WHERE user = ?", [$new_id, $temp_id]);
    $database->query("UPDATE videos SET author = ? WHERE author = ?", [$new_id, $temp_id]);

    $pfpOld = SB_DYNAMIC_PATH . '/pfp/' . $user["name"] . '.png';
    $pfpNew = SB_DYNAMIC_PATH . '/pfp/' . $new_id . '.png';
    $bannerOld = SB_DYNAMIC_PATH . '/banners/' . $user["name"] . '.png';
    $bannerNew = SB_DYNAMIC_PATH . '/banners/' . $new_id . '.png';

    if (file_exists($pfpOld)) {
        if (!rename($pfpOld, $pfpNew)) {
            echo "Failed to rename profile picture from $pfpOld to $pfpNew";
        }
    }

    if (file_exists($bannerOld)) {
        if (!rename($bannerOld, $bannerNew)) {
            echo "Failed to rename banner from $bannerOld to $bannerNew";
        }
    }
}