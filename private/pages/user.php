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

namespace OpenSB;

global $auth, $database, $twig, $orange;

use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\UploadData;
use SquareBracket\UploadQuery;
use SquareBracket\UserCustomizationData;
use SquareBracket\UserFlags;
use SquareBracket\UserRoleEnum;
use SquareBracket\Utilities;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

$username = $path[2] ?? null;

if (isset($_GET['name'])) Utilities::redirect('/user/' . $_GET['name']);

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
        Utilities::notifyBanner("This user does not exist.", "/");
    }
}

if ($user_ban_data = $database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$data["id"]])) {
    if (!$auth->userHasRole(UserRoleEnum::Administrator)) {
        Utilities::notifyBanner("This user is banned.", "/");
    }
}

$user_submissions_query_limit = 12;

if ($options["skin"] == "bootstrap" && $options["theme"] == "alpha2") {
    $user_submissions_query_limit = 1;
}

// TODO: redo this
function handleFeaturedSubmission($database, $data): false|array
{
    global $orange, $auth;

    // handle featured submission
    // if user hasn't specified anything, then use latest submission, if that doesn't exist, do not bother.
    $featured_id = $database->fetch("SELECT video_id FROM uploads v WHERE v.id = ?", [$data["featured_submission"]]);

    if ($featured_id == 0 || !$featured_id) {
        $featured_id = $database->fetch(
            "SELECT video_id FROM uploads v WHERE v.author = ? ORDER BY v.time DESC",
            [$data["id"]]
        );
        if (!isset($featured_id["video_id"])) {
            return false;
        }
        if ($featured_id == 0) {
            return false;
        }
    }

    $submission = new UploadData($database, $featured_id["video_id"]);
    $submission_data = $submission->getData();
    $bools = $submission->getUploadFlagsArray();

    // IF:
    // * The submission is taken down, and/or
    // * The submission no longer exists and/or
    // * The submission's author is not the user whose profile we're looking at and/or
    // * The submission is not available to guests and the user isn't signed in and/or
    // * TODO: The submission is privated...
    // then simply just return false, so we don't show the featured submission.
    if (
        $submission->getTakedown()
        || !$submission_data
        || ($submission_data["author"] != $data["id"])
        || ($bools["block_guests"] && !$auth->isUserLoggedIn())
    ) {
        return false;
    } else {
        // HACK: we have to use Utilities::makeUploadArray since there is somehow
        // no standardized way to handle upload arrays.
        return Utilities::makeUploadArray($database, [0 => $submission_data])[0];
    }
}

$user_submissions = $submission_query->query("v.time desc", $user_submissions_query_limit, "v.author = ?", [$data["id"]]);

$user_journals =
    $database->fetchArray(
        $database->query("SELECT j.* FROM journals j WHERE
                         j.author = ? 
                         ORDER BY j.date 
                         DESC LIMIT 8", [$data["id"]])
    );

$is_own_profile = ($data["id"] == $auth->getUserID());

if ($is_own_profile || $auth->userHasRole(UserRoleEnum::Administrator)) {
    $old_usernames = $database->fetchArray($database->query("SELECT * FROM user_old_names WHERE user = ?", [$data["id"]]));
} else {
    $old_usernames = [];
}

$flags = UserFlags::toArray($data["u_flags"]);

if ($flags["profile_customization_enabled"]) {
    $profile_customization_data = new UserCustomizationData($database, $data["id"]);
} else {
    $profile_customization_data = null;
}

$comments = new CommentData($database, CommentLocation::Profile, $data["id"]);

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["id"]]);
$followed = Utilities::isFollowingUser($data["id"]);
$views = $database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$data["id"]]);

$featured_submission = handleFeaturedSubmission($database, $data);

$profile_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["customcolor"],
    "about" => ($data['about'] ?? false),
    "joined" => $data["joined"],
    "connected" => $data["lastview"],
    "is_current" => $is_own_profile,
    "featured_submission" => $featured_submission,
    "submissions" => Utilities::makeUploadArray($database, $user_submissions),
    "journals" => Utilities::makeJournalArray($database, $user_journals),
    "comments" => $comments->getComments(),
    "followers" => $followers,
    "following" => $followed,
    "is_staff" => ($data["powerlevel"] > 1),
    "views" => $views,
    "old_usernames" => $old_usernames,
    "customization" => $profile_customization_data?->getData() ?? false,
    "ban_data" => $user_ban_data ?? [],
];

// calculate the color used for profile banner on the bootstrap frontend
// the original implementation for this used a scss php compiler library thing but that is fucking stupid and it'll
// slow down the site, so lets just approximate this.
if ($orange->getLocalOptions()["skin"] == "bootstrap") {
    function adjustCssColorBrightness($hex, $percent): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // adjust brightness
        $r = max(0, min(255, (int)round($r + $r * $percent / 100)));
        $g = max(0, min(255, (int)round($g + $g * $percent / 100)));
        $b = max(0, min(255, (int)round($b + $b * $percent / 100)));

        // now convert this back into hex
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
            . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
            . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }

    // approximate bootstrap's text-contrast scss function
    $hex = ltrim($data["customcolor"], '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $colorBrightness = round(($r * 299 + $g * 587 + $b * 114) / 1000);
    $textColor = ($colorBrightness < 130) ? 'white' : 'black'; // 255/2 ≈ 130

    // generate the gradient colors
    $gradientStart = adjustCssColorBrightness($data["customcolor"], 0);
    $gradientMid = adjustCssColorBrightness($data["customcolor"], -7);
    $gradientEnd = adjustCssColorBrightness($data["customcolor"], -15);

    $primaryStart = adjustCssColorBrightness($data["customcolor"], 8);
    $primaryMid = $data["customcolor"];
    $primaryEnd = adjustCssColorBrightness($data["customcolor"], -4);

    // now turn this into css
    $profile_data["bootstrap_profile_css"] = "
.bg-custom-profile {
    background-image: linear-gradient({$gradientStart}, {$gradientMid} 50%, {$gradientEnd});
    color: {$textColor};
}

.bg-primary {
    background-image: linear-gradient({$primaryStart}, {$primaryMid} 60%, {$primaryEnd});
}
";
}

echo $twig->render("profile.twig", [
    'data' => $profile_data,
]);
