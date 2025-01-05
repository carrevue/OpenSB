<?php

namespace OpenSB;

global $twig, $auth, $database;

use SquareBracket\Utilities;

global $auth;

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
}

// we shouldn't let banned users change settings.
if ($auth->getUserBanData()) {
    Utilities::notifyBanner("You cannot proceed with this action.", "/");
}

if (isset($_POST['save'])) {
    global $auth, $storage;

    $title = htmlspecialchars($_POST['title']) ?? null;

    // if display name is set to empty, fallback to our current username.
    $title = trim($title) === '' ? $auth->getUserData()["name"] : $title;

    $about = $_POST['about'] ?? null;

    $currentPass = ($_POST['current_pass'] ?? null);
    $pass = ($_POST['pass'] ?? null);
    $pass2 = ($_POST['pass2'] ?? null);
    $new_username = $_POST['new_username'] ?? null;

    $customcolor = ($_POST['customcolor'] ?? '#523bb8');

    $profile_layout = ($_POST['profile-layout'] ?? 0);

    if ($auth->isUserOver18()) {
        $rating = isset($_POST['rating']) && $_POST['rating'] === 'true' ? 'mature' : 'general';
    } else {
        $rating = 'general';
    }

    $blacklisted_tags = ($_POST['blacklisted_tags'] ?? $auth->getDefaultBlacklistedTags());

    if ($blacklisted_tags === '') {
        $parsed_tags = [];
    } else {
        $parsed_tags = preg_split('/[\s,]+/', trim($blacklisted_tags, ","));
    }

    $error = '';

    $password = $database->fetch("SELECT password FROM users WHERE id = ?", [$auth->getUserID()])["password"];
    if ($currentPass && $pass && $pass2) {
        if (password_verify($currentPass, $password)) {
            if ($pass == $pass2) {
                $database->query("UPDATE users SET password = ?, token = ? WHERE id = ?",
                    [password_hash($pass, PASSWORD_DEFAULT), bin2hex(random_bytes(32)), $auth->getUserID()]);

                Utilities::notifyBanner("Your password has been changed.", "/login");
            } else {
                $error .= " The new passwords aren't identical.";
            }
        } else {
            $error .= "Your current password is incorrect.";
        }
    }

    if (strlen($title) > 100) {
        $error .= "Your display name is too long.";
    }

    $username_changed = false;

    if ($currentPass && isset($new_username)) {
        if ($new_username != $auth->getUserData()["name"]) {
            if (password_verify($currentPass, $password)) {
                $old_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$auth->getUserID()])["name"];

                $is_old_username = $database->result("SELECT COUNT(*) FROM user_old_names WHERE user = ? AND old_name = ?", [$auth->getUserID(), $new_username]);

                if ($is_old_username) {
                    // still validate any old usernames because this code was actually broken and
                    // didn't validate anything (sql was sanitized tho), at all! -chaziz 6/28/2024
                    $error .= Utilities::validateUsername($new_username, $database, false);
                    $database->query("INSERT INTO user_old_names (user, old_name, time) VALUES (?, ?, ?)",
                        [$auth->getUserID(), $old_username, time()]);

                    if (!$error) {
                        $database->query("UPDATE users SET name = ? WHERE id = ?", [$new_username, $auth->getUserID()]);
                        $username_changed = true;
                    }
                } else {
                    $error .= Utilities::validateUsername($new_username, $database);
                    if ($database->result("SELECT COUNT(*) FROM user_old_names WHERE user != ? AND old_name = ?", [$auth->getUserID(), $new_username])) {
                        $error .= "You cannot use someone else's previous username.";
                    }

                    if (!$error) {
                        $last_entry_time = $database->result("SELECT MAX(time) FROM user_old_names WHERE user = ?", [$auth->getUserID()]);

                        if (!$last_entry_time || (time() - $last_entry_time >= 2592000)) {
                            $database->query("INSERT INTO user_old_names (user, old_name, time) VALUES (?, ?, ?)",
                                [$auth->getUserID(), $old_username, time()]);
                            $database->query("UPDATE users SET name = ? WHERE id = ?", [$new_username, $auth->getUserID()]);
                            $username_changed = true;
                        } else {
                            $days_left = ceil((2592000 - (time() - $last_entry_time)) / 86400);
                            $error .= "Please wait until $days_left days to change your username.";
                        }
                    }
                }
            }
        }
    }

    if (!empty($_FILES['profilePicture']['name'])) {
        $name = $_FILES['profilePicture']['name'];
        $temp_name = $_FILES['profilePicture']['tmp_name'];
        $ext = pathinfo($_FILES['profilePicture']['name'], PATHINFO_EXTENSION);
        $storage->processProfilePicture($temp_name, $auth->getUserData()["id"]);
    }

    if (!empty($_FILES['profileBanner']['name'])) {
        $name = $_FILES['profileBanner']['name'];
        $temp_name = $_FILES['profileBanner']['tmp_name'];
        $ext = pathinfo($_FILES['profileBanner']['name'], PATHINFO_EXTENSION);
        $storage->processProfileBanner($temp_name, $auth->getUserData()["id"]);
    }

    if (!$error) {
        $database->query("UPDATE users SET 
                 title = ?, 
                 about = ?, 
                 comfortable_rating = ?, 
                 customcolor = ?, 
                 profile_layout = ?,
                 blacklisted_tags = ?
                 WHERE id = ?",
            [$title, $about, $rating, $customcolor, $profile_layout, json_encode($parsed_tags), $auth->getUserID()]);

        if ($username_changed) {
            // avoids "This user does not exist." error since $auth by this point still uses outdated data.
            // poor design? pretty much, yea. -chaziz 6/18/2024
            $url = "/user/" . $new_username;
        } else {
            $url = "/user/" . $auth->getUserData()["name"];
        }

        Utilities::notifyBanner("Your settings have been successfully updated.", $url, "success");
    } else {
        Utilities::notifyBanner($error, "/settings.php");
    }
}

echo $twig->render('settings.twig', ['isUserOver18' => $auth->isUserOver18()]);
