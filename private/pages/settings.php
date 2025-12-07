<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou

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

namespace OpenSB\Pages;

global $sb, $twig, $auth, $database;

use Random\RandomException;
use OpenSB\UserFlags;
use OpenSB\Utilities;
use OpenSB\UserCustomizationFont;

$options = $sb->getLocalOptions();

$trinium_fonts_array = UserCustomizationFont::getAll();

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

// we shouldn't let banned users change settings.
if ($auth->getUserBanData()) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if ($auth->getUserFlags(true)["unverified"]) {
    http_response_code(401);
    echo $twig->render('unverified.twig');
    die();
}

if ($options["skin"] == "trinium") {
    // check if this user has an entry in the profile customization table
    $profile_color_data = $database->fetch(
        "SELECT * FROM user_profile_customization WHERE user = ?",
        [$auth->getUserData()["id"]]
    );
}

if (isset($_POST['save'])) {
    $flags = $auth->getUserFlags();

    $title = htmlspecialchars($_POST['title']) ?? null;

    // if display name is set to empty, fallback to our current username.
    $title = trim($title) === '' ? $auth->getUserData()["name"] : $title;

    $about = $_POST['about'] ?? null;

    $currentPass = ($_POST['current_pass'] ?? null);
    $pass = ($_POST['pass'] ?? null);
    $pass2 = ($_POST['pass2'] ?? null);
    $new_username = $_POST['new_username'] ?? null;

    if ($options["skin"] == "trinium") {
        $enable_customization = $_POST['enable_customization'] ?? false;
        $font = $_POST['font'] ?? 'default';

        // the colors
        $userlink_color = substr($_POST['userlink_color'] ?? '#0069B4', 0, 7);
        $background_color = substr($_POST['background_color'] ?? '#FFFFFF', 0, 7);
        $title_color = substr($_POST['title_color'] ?? '#333333', 0, 7);
        $link_color = substr($_POST['link_color'] ?? '#0033cc', 0, 7);
        $basic_box_border_color = substr($_POST['basic_box_border_color'] ?? '#666666', 0, 7);
        $basic_box_background_color = substr($_POST['basic_box_background_color'] ?? '#FFFFFF', 0, 7);
        $basic_box_text_color = substr($_POST['basic_box_text_color'] ?? '#000000', 0, 7);
        $highlight_box_border_color = substr($_POST['highlight_box_border_color'] ?? '#666666', 0, 7);
        $highlight_box_background_color = substr($_POST['highlight_box_background_color'] ?? '#E6E6E6', 0, 7);
        $highlight_box_text_color = substr($_POST['highlight_box_text_color'] ?? '#000000', 0, 7);
    }

    if ($auth->isUserOver18() && !$sb->isChazizSquareBracketInstance()) {
        $rating = isset($_POST['rating']) && $_POST['rating'] === 'true' ? 'mature' : 'general';
    } else {
        $rating = 'general';
    }

    $blacklisted_tags = ($_POST['blacklisted_tags'] ?? $auth->getDefaultTagBlacklist());

    if ($blacklisted_tags === '') {
        $parsed_tags = [];
    } else {
        $parsed_tags = preg_split('/[\s,]+/', trim($blacklisted_tags, ","));
    }

    if ($enable_customization) {
        $flags |= UserFlags::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value;
    }

    $error = '';

    $password = $database->fetch("SELECT password FROM users WHERE id = ?", [$auth->getUserID()])["password"];
    if ($currentPass && $pass && $pass2) {
        if (password_verify($currentPass, $password)) {
            if ($pass == $pass2) {
                try {
                    $new_token = bin2hex(random_bytes(32));
                } catch (RandomException $e) {
                    Utilities::notifyBanner("notify_token_generation_fail", "/settings");
                }

                $database->query(
                    "UPDATE users SET password = ?, token = ? WHERE id = ?",
                    [password_hash($pass, PASSWORD_DEFAULT), $new_token, $auth->getUserID()]
                );

                Utilities::notifyBanner("notify_password_changed", "/login", "success");
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
                    $database->query(
                        "INSERT INTO user_old_names (user, old_name, timestamp) VALUES (?, ?, ?)",
                        [$auth->getUserID(), $old_username, time()]
                    );

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
                        $last_entry_time = $database->result("SELECT MAX(timestamp) FROM user_old_names WHERE user = ?", [$auth->getUserID()]);

                        if (!$last_entry_time || (time() - $last_entry_time >= 2592000)) {
                            $database->query(
                                "INSERT INTO user_old_names (user, old_name, timestamp) VALUES (?, ?, ?)",
                                [$auth->getUserID(), $old_username, time()]
                            );
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
        $sb->getStorageClass()->processProfilePicture($temp_name, $auth->getUserData()["id"]);
    }

    if (!empty($_FILES['profileBanner']['name'])) {
        $name = $_FILES['profileBanner']['name'];
        $temp_name = $_FILES['profileBanner']['tmp_name'];
        $ext = pathinfo($_FILES['profileBanner']['name'], PATHINFO_EXTENSION);
        $sb->getStorageClass()->processProfileBanner($temp_name, $auth->getUserData()["id"]);
    }

    if (!$error) {
        $database->query(
            "UPDATE users SET 
                 title = ?, 
                 about = ?, 
                 comfortable_rating = ?, 
                 userlink_color = ?, 
                 flags = ?,
                 blacklisted_tags = ?
                 WHERE id = ?",
            [$title, $about, $rating, $userlink_color, $flags, json_encode($parsed_tags), $auth->getUserID()]
        );

        if ($options["skin"] == "trinium") {
            if ($profile_color_data) {
                // if so, update their customizations
                $database->query("
                    UPDATE user_profile_customization SET
                        font = ?,
                        background_color = ?,
                        title_color = ?,
                        link_color = ?,
                        basic_box_border_color = ?,
                        basic_box_background_color = ?,
                        basic_box_text_color = ?,
                        highlight_box_border_color = ?,
                        highlight_box_background_color = ?,
                        highlight_box_text_color = ?
                    WHERE user = ?
                ", [
                    $font,
                    $background_color,
                    $title_color,
                    $link_color,
                    $basic_box_border_color,
                    $basic_box_background_color,
                    $basic_box_text_color,
                    $highlight_box_border_color,
                    $highlight_box_background_color,
                    $highlight_box_text_color,
                    $auth->getUserID()
                ]);
            } else {
                // if not, initialize the shit.
                $database->query("
                    INSERT INTO user_profile_customization (
                        user, font, background_color, title_color, link_color,
                        basic_box_border_color, basic_box_background_color, basic_box_text_color,
                        highlight_box_border_color, highlight_box_background_color, highlight_box_text_color
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $auth->getUserID(),
                    $font,
                    $background_color,
                    $title_color,
                    $link_color,
                    $basic_box_border_color,
                    $basic_box_background_color,
                    $basic_box_text_color,
                    $highlight_box_border_color,
                    $highlight_box_background_color,
                    $highlight_box_text_color
                ]);
            }
        }

        if ($username_changed) {
            // avoids "notify_invalid_user" error since $auth by this point still uses outdated data.
            // poor design? pretty much, yea. -chaziz 6/18/2024
            $url = "/user/" . $new_username;
        } else {
            $url = "/user/" . $auth->getUserData()["name"];
        }

        Utilities::notifyBanner("notify_successfully_updated_settings", $url, "success");
    } else {
        Utilities::notifyBanner($error, "/settings");
    }
}

echo $twig->render('settings.twig', [
    'isUserOver18' => $auth->isUserOver18(),
    'flags' => $auth->getUserFlags(true),
    'profile_color_data' => $profile_color_data ?? [],
    'trinium_fonts' => $trinium_fonts_array,
]);
