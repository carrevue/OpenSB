<?php

namespace OpenSB;

global $auth, $database, $twig, $orange;

use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\ProfileLayoutEnum;
use SquareBracket\Utilities;
use SquareBracket\UploadQuery;

$submission_query = new UploadQuery($database);

$options = $orange->getLocalOptions();

$username = $path[2] ?? null;

if (isset($_GET['name'])) Utilities::redirect('/user/' . $_GET['name']);

$data = $database->fetch("SELECT * FROM users u WHERE u.name = ?", [$username]);

if (!$data)
{
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

if ($database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$data["id"]]))
{
    Utilities::notifyBanner("This user is banned.", "/");
}

$user_submissions_query_limit = 12;

if ($options["skin"] == "bootstrap" && $options["theme"] == "alpha2") {
    $user_submissions_query_limit = 1;
}

$user_submissions = $submission_query->query("v.time desc", $user_submissions_query_limit, "v.author = ?", [$data["id"]]);

$user_journals =
    $database->fetchArray(
        $database->query("SELECT j.* FROM journals j WHERE
                         j.author = ? 
                         ORDER BY j.date 
                         DESC LIMIT 20", [$data["id"]]));

$is_own_profile = ($data["id"] == $auth->getUserID());

if ($is_own_profile || $auth->isUserAdmin()) {
    $old_usernames = $database->fetchArray($database->query("SELECT * FROM user_old_names WHERE user = ?", [$data["id"]]));
} else {
    $old_usernames = [];
}

// placeholder
$profile_color_data = [
    "font" => '"Comic Sans MS", "Comic Sans", cursive;',
    "background_color" => "#FFFFFF",
    "title_color" => "#333333",
    "link_color" => "#0033CC",
    "basic_box_border_color" => "#666666",
    "basic_box_background_color" => "#FFFFFF",
    "basic_box_text_color" => "#000000",
    "highlight_box_border_color" => "#666666",
    "highlight_box_background_color" => "#E6E6E6",
    "highlight_box_text_color" => "#000000",
];

$comments = new CommentData($database, CommentLocation::Profile, $data["id"]);

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
    "submissions" => Utilities::makeUploadArray($database, $user_submissions),
    "journals" => Utilities::makeJournalArray($database, $user_journals),
    "comments" => $comments->getComments(),
    "followers" => $followers,
    "following" => $followed,
    "is_staff" => ($data["powerlevel"] > 1),
    "views" => $views,
    "old_usernames" => $old_usernames,
    "customization" => $profile_color_data,
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
    'page_name' => "user",
]);