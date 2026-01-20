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

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\CommentData;
use OpenSB\CommentLocation;
use OpenSB\UploadData;
use OpenSB\UploadQuery;
use OpenSB\UserCustomizationData;
use OpenSB\UserFlags;
use OpenSB\UserRoleEnum;
use OpenSB\FakeUser;

$upload_query = new UploadQuery($sb);

$options = $sb->getLocalOptions();

if (isset($_GET['name'])) Utilities::redirect('/user/' . $_GET['name'], 301);

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data) {
    // check if this username was used before and was changed out of.
    $old_username_data = $database->fetch("SELECT user FROM user_old_names WHERE old_name = ?", [$username]);

    if ($old_username_data) {
        // if so, attempt to fetch the user's current name through their id
        $new_username = $database->fetch("SELECT name FROM users WHERE id = ?", [$old_username_data['user']])["name"];
        if ($new_username) {
            Utilities::redirect('/user/' . $new_username, 301);
        } else {
            // if for whatever reason this leads to nowhere (eg: deleted user or 
            // half-assed prod blacklisting), return to homepage.
            Utilities::notifyBanner("notify_invalid_user", "/");
        }
    } else {
        // check if this could be a fake user
        $fake_user_data = FakeUser::getFakeUserFromName($username);
        if ($fake_user_data) {
            $data = $fake_user_data;
        } else {
            Utilities::notifyBanner("notify_invalid_user", "/");
        }
    }
}

if ($user_ban_data = $database->fetch("SELECT * FROM user_bans WHERE user = ?", [$data["id"]])) {
    if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
        Utilities::notifyBanner("notify_banned_user", "/");
    }
}

if ($options["skin"] == "finalium") {
    $user_uploads_query_limit = 4;
} else {
    $user_uploads_query_limit = 12;
}

// TODO: redo this
function handleFeaturedUpload($database, $data): false|array
{
    global $sb, $auth;

    // handle featured upload
    // if user hasn't specified anything, then use latest upload, if that doesn't exist, do not bother.
    $featured_id = $database->fetch("SELECT upload_id FROM uploads v WHERE v.id = ?", [$data["featured_upload"]]);

    if ($featured_id == 0 || !$featured_id) {
        $featured_id = $database->fetch(
            "SELECT upload_id FROM uploads v WHERE v.author = ? ORDER BY v.timestamp DESC",
            [$data["id"]]
        );
        if (!isset($featured_id["upload_id"])) {
            return false;
        }
        if ($featured_id == 0) {
            return false;
        }
    }

    $upload = new UploadData($database, $featured_id["upload_id"]);
    $upload_data = $upload->getData();
    $bools = $upload->getFlagArray();

    // IF:
    // * The upload is taken down, and/or
    // * The upload no longer exists and/or
    // * The upload's author is not the user whose profile we're looking at and/or
    // * The upload is not available to guests and the user isn't signed in and/or
    // * TODO: The upload is privated...
    // then simply just return false, so we don't show the featured upload.
    if (
        $upload->isTakenDown()
        || !$upload_data
        || ($upload_data["author"] != $data["id"])
        || ($bools["block_guests"] && !$auth->isUserLoggedIn())
    ) {
        return false;
    } else {
        // HACK: we have to use Utilities::makeUploadArray since there is somehow
        // no standardized way to handle upload arrays.
        return Utilities::makeUploadArray($database, [0 => $upload_data])[0];
    }
}

$user_uploads = $upload_query->query("v.timestamp desc", $user_uploads_query_limit, "v.author = ?", [$data["id"]]);

if ($options["skin"] == "bootstrap") {
    $user_journal_limit = 3;
} else {
    $user_journal_limit = 8;
}

$user_journals =
    $database->fetchArray(
        $database->query("SELECT j.* FROM journals j WHERE
                         j.author = ? 
                         ORDER BY j.timestamp 
                         DESC LIMIT ?", [$data["id"], $user_journal_limit])
    );

$is_own_profile = ($data["id"] == $auth->getUserID());

$flags = UserFlags::toArray($data["flags"]);

if ($flags["profile_customization_enabled"]) {
    $profile_customization_data = new UserCustomizationData($database, $data["id"]);
} else {
    $profile_customization_data = null;
}

if (
    $sb->getLocalOptions()["skin"] != "bootstrap" && $sb->getLocalOptions()["skin"] != "finalium"
) {
    $comment_data = new CommentData($database, CommentLocation::Profile, $data["id"]);
    $comments = $comment_data->getComments(10);
} else {
    $comments = [];
}

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["id"]]);
$followed = Utilities::isFollowingUser($data["id"]);
$views = $database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$data["id"]]);

$featured_upload = handleFeaturedUpload($database, $data);

$page_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["userlink_color"],
    "about" => ($data['about'] ?? false),
    "joined" => $data["joined"],
    "connected" => $data["last_seen"],
    "is_current" => $is_own_profile,
    "featured_upload" => $featured_upload,
    "uploads" => Utilities::makeUploadArray($database, $user_uploads),
    "journals" => Utilities::makeJournalArray($database, $user_journals),
    "comments" => $comments,
    "followers" => $followers,
    "following" => $followed,
    "is_staff" => ($data["powerlevel"] > 1),
    "views" => $views,
    "customization" => $profile_customization_data?->getData() ?? false,
    "ban_data" => $user_ban_data ?? [],
];

if ($sb->getLocalOptions()["skin"] == "bootstrap") {
    $page_data["bootstrap_profile_css"] = Utilities::makeBootstrapFrontendProfileGradient($data["userlink_color"]);
}

echo $twig->render("profile.twig", [
    'data' => $page_data,
]);
