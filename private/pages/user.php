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
    "link_color" => "#0033CC",
    "label_color" => "#666666",
    //"opacity" => "95", // pretty sure the default was 95%
    "basic_box_border_color" => "#666666",
    "basic_box_background_color" => "#FFFFFF",
    "basic_box_text_color" => "#000000",
    "highlight_box_background_color" => "#E6E6E6",
    "highlight_box_text_color" => "#666666",
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

echo $twig->render("profile.twig", [
    'data' => $profile_data,
    'page_name' => "user",
]);