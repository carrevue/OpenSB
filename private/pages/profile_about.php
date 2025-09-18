<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

global $auth, $database, $twig, $sb;

use BluffingoCore\CoreUtilities;
use OpenSB\CommentData;
use OpenSB\CommentLocation;
use OpenSB\UploadData;
use OpenSB\UploadQuery;
use OpenSB\UserCustomizationData;
use OpenSB\UserFlags;
use OpenSB\UserRoleEnum;
use OpenSB\Utilities;

$submission_query = new UploadQuery($database);

$options = $sb->getLocalOptions();

// if we're not on finalium, redirect to the normal profile page.
if ($sb->getLocalOptions()["skin"] != "finalium") {
    CoreUtilities::redirect("/user/$username");
}

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, redirect to the new profile.
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        http_response_code(301);
        header("Location: /user/$new_username");
        exit();
    } else {
        Utilities::notifyBanner("notify_invalid_user", "/");
    }
}

if ($user_ban_data = $database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$data["id"]])) {
    if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
        Utilities::notifyBanner("notify_banned_user", "/");
    }
}

$user_submissions_query_limit = 12;

$user_submissions = $submission_query->query("v.time desc", $user_submissions_query_limit, "v.author = ?", [$data["id"]]);

if ($options["skin"] == "bootstrap") {
    $user_journal_limit = 3;
} else {
    $user_journal_limit = 8;
}

$user_journals =
    $database->fetchArray(
        $database->query("SELECT j.* FROM journals j WHERE
                         j.author = ? 
                         ORDER BY j.date 
                         DESC LIMIT ?", [$data["id"], $user_journal_limit])
    );

$is_own_profile = ($data["id"] == $auth->getUserID());

$flags = UserFlags::toArray($data["u_flags"]);

if ($flags["profile_customization_enabled"]) {
    $profile_customization_data = new UserCustomizationData($database, $data["id"]);
} else {
    $profile_customization_data = null;
}

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["id"]]);
$followed = Utilities::isFollowingUser($data["id"]);
$views = $database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$data["id"]]);

$profile_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["customcolor"],
    "about" => ($data['about'] ?? false),
    "joined" => $data["joined"],
    "connected" => $data["lastview"],
    "is_current" => $is_own_profile,
    "followers" => $followers,
    "following" => $followed,
    "is_staff" => ($data["powerlevel"] > 1),
    "views" => $views,
    "customization" => $profile_customization_data?->getData() ?? false,
];

/*
if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $profile_data["bootstrap_profile_css"] = Utilities::makeBootstrapFrontendProfileGradient($data["customcolor"]);
}
*/

echo $twig->render("profile_about.twig", [
    'data' => $profile_data,
]);
