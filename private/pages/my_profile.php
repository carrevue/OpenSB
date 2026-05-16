<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

namespace Pages;

global $sb, $twig, $auth, $database;

use Random\RandomException;
use Data\User\UserFlags;
use Core\Utilities;
use Data\UserProfile\CustomizationFontEnum;

$options = $sb->getLocalOptions();

$trinium_fonts_array = CustomizationFontEnum::getAll();

if (!$auth->isLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

if ($auth->isBanned() || $auth->getUserFlags(true)["unverified"]) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

$unicode_blacklist = [
    // checkmarks
    "☑", "☑️", "✅", "✓", "✔", "✔", 
    // shields
    "🛡️", "⛉", "⛊", "⛨",
];

if ($options["skin"] == "trinium") {
    // check if this user has an entry in the profile customization table
    $profile_color_data = $database->fetch(
        "SELECT * FROM user_profile_customization WHERE user = ?",
        [$auth->getUserData()["id"]]
    );
}

$error = '';

if (isset($_POST['save'])) {
    $flags = $auth->getUserFlags();

    $title = htmlspecialchars($_POST['title']) ?? null;

    // if display name is set to empty, fallback to our current username.
    $title = trim($title) === '' ? $auth->getUserData()["name"] : $title;
    $title = str_replace($unicode_blacklist, '', $title);

    if (strlen($title) > 100) {
        $error .= "Your display name is too long.";
    }

    $about = $_POST['about'] ?? null;

    if ($options["skin"] == "trinium") {
        $enable_customization = $_POST['enable_customization'] ?? false;
        $font = $_POST['font'] ?? 'default';

        if ($enable_customization) {
            $flags |= UserFlags::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value;
        } else {
            $flags &= ~UserFlags::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value;
        }

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
    } else {
        $userlink_color = $auth->getUserData()["userlink_color"];
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

        if ($sb->getLocalOptions()["skin"] == "finalium") {
            $sb->getStorageClass()->processFinaliumProfileBanner($temp_name, $auth->getUserData()["id"]);
        } else {
            $sb->getStorageClass()->processTriniumProfileBanner($temp_name, $auth->getUserData()["id"]);
        }
    }

    if (!$error) {
        $database->query(
            "UPDATE users SET 
                 title = ?, 
                 about = ?, 
                 userlink_color = ?, 
                 flags = ?
                 WHERE id = ?",
            [$title, $about, ($userlink_color ?? ($auth->getUserData()["userlink_color"] ?? '#0069B4')), $flags, $auth->getUserID()]
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

        Utilities::notifyBanner("notify_successfully_updated_settings", "/user/" . $auth->getUserData()["name"], "success");
    } else {
        Utilities::notifyBanner($error, "/settings");
    }
}

echo $twig->render('my_profile.twig', [
    'flags' => $auth->getUserFlags(true),
    'profile_color_data' => $profile_color_data ?? [],
    'trinium_fonts' => $trinium_fonts_array,
]);