<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

namespace Pages;

global $twig, $database, $auth, $sb;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use Data\Comment\CommentData;
use Data\Comment\CommentLocation;
use Data\Upload\UploadData;
use Data\Upload\UploadQuery;
use Data\User\UserRoleEnum;
use Core\Utilities;
use Data\Upload\UploadVisibilityEnum;
use Data\Recommendation\RecommendationQuery;
use Data\Recommendation\LegacyRecommendationQuery;

$options = $sb->getLocalOptions();

function handle_error(string $message, string ...$args) {
    global $sb, $twig; // Lol

    if ($sb->getSkinThemeOptions()["view_use_error_page"] ?? false) {
        $localization = $sb->getLocalizationClass();
        $text = $localization->translate($message, ...$args);

        echo $twig->render('watch_error.twig', [
            'error' => $text,
        ]);
        die();
    } else {
        // go back to homepage with a notification (or something else if specified)
        Utilities::notifyBanner($message, "/", "danger", $args);
    }
}

if (Utilities::isClassicSkin()) {
    if (isset($_GET["v"])) { // get "v" parameter and set that as the id
        $id = $_GET["v"];
    } elseif (isset($id)) { // if id is already defined, this is likely a trinium-style url, so redirect to watch
        Utilities::redirect('/watch?v=' . $id, 301);
    }
} else {
    if (isset($_GET["v"])) { // handle awkward edgecase
        Utilities::redirect('/view/' . $_GET['v'], 301);
    }
}

// video ids in the original fulptube were actually the upload timestamp but encoded in base64, so
// theoretically we could use this and opensb's "original_timestamp" to redirect "recovered" 
// OG FulpTube videos into their current FulpTube/squareBracket counterparts.
// ex: https://fulptube.rocks/watch?v=MTYxODY5MDE0MzU=02 -> https://fulptube.rocks/watch?v=i4caVqnxdKM
// -chaziz 02/14/2026
if ($sb->isFulpTubeMode()) {
    if (preg_match('/^MTY.*=\d{2}$/', $id)) {
        $og_fulptube_timestamp = base64_decode(substr($id, 0, strpos($id, '=') - 1));
        $id_for_redirect = $database->result("SELECT upload_id FROM uploads where original_timestamp = ?", [$og_fulptube_timestamp]);

        if ($id_for_redirect) {
            Utilities::redirect('/watch?v=' . $id_for_redirect, 301);
        } else {
            handle_error("notify_original_fulptube_video");
        }
    }
}

if (!isset($id)) {
    handle_error("notify_invalid_upload");
}

$upload = new UploadData($database, $id);

// check if the upload has been taken down.
$isStaff = $auth->userHasRole(UserRoleEnum::Moderator) && $auth->hasUserAuthenticatedAsStaff();

if ($upload->isTakenDown() || $upload->isAuthorBanned()) {
    if ($isStaff) {
        Utilities::redirect('/dashboard/uploads/' . $id);
    }

    if (!$isStaff) {
        if ($upload->isTakenDown()) handle_error("notify_taken_down_upload");
        if ($upload->isAuthorBanned()) handle_error("notify_upload_author_banned", $sb->getBrandingSettings()["name"]);
    }
}

if ($upload->isDeleted()) {
    handle_error("notify_deleted_upload");
}

$data = $upload->getData();
if (!$data) {
    handle_error("notify_invalid_upload");
}

$owner = $auth->getUserID() == $data["author"];

if ($data["visibility"] == UploadVisibilityEnum::Private->value && !$owner) {
    handle_error("notify_private_upload");
}

$tagBlacklist = $auth->getUserTagBlacklist();

if (isset($data["tags"])) {
    $decodedTags = json_decode($data["tags"]);
    if ($decodedTags !== null) {
        foreach ($decodedTags as $tag) {
            if (in_array($tag, $tagBlacklist)) {
                if ($auth->isLoggedIn()) {
                    handle_error("notify_upload_tag_blacklist_logged_in");
                } else {
                    handle_error("notify_upload_tag_blacklist_logged_out");
                }
            }
        }
    }
}

$author_info = $upload->getAuthorData();

$tags = $upload->getTags();

$following = Utilities::isFollowingUser($data["author"]);

// TODO: this feature is unused.
//$favorites = $database->result("SELECT COUNT(upload_id) FROM user_favorites WHERE upload_id=?", [$id]);

$flags = $upload->getFlagArray();

if ($flags["block_guests"] && !$auth->isLoggedIn()) {
    handle_error("notify_login_required_view_upload");
}

if ($flags["mature"] && !$auth->getUserFlags(true)["mature_content_access"]) {
    handle_error("notify_cannot_access_mature_upload");
}

$ip = Utilities::getIpAddress();

$CrawlerDetect = new CrawlerDetect;

$type = $auth->isLoggedIn() ? "user" : "guest";

// probably shit
/*
if (!$CrawlerDetect->isCrawler() && !Utilities::isTorExitNode(Utilities::getIpAddress())) {
    $ratelimit = false;

    // add a limit of one view per minute on guests. this is to deter other forms of crawlers/bots that may
    // not be properly caught by crawlerdetect.
    if (
        !$auth->isLoggedIn() &&
        $database->result("SELECT COUNT(*) FROM upload_views WHERE user=? AND timestamp > 60", [$ip])
    ) {
        $ratelimit = true;
    }

    if (
        $database->result("SELECT COUNT(upload_id) FROM upload_views WHERE upload_id=? AND user=?", [$id, $ip]) < 1
        && !$ratelimit
    ) {
        $database->query(
            "INSERT INTO upload_views (upload_id, user, timestamp, type) VALUES (?,?,?,?)",
            [$id, $ip, time(), $type]
        );

        // increment the indexed view count. this might go out of sync eventually, but this can be fixed through
        // the recount_views.php script.
        $new_views = $data["views"] + 1;
        $database->query(
            "UPDATE uploads SET views = ? WHERE id = ?",
            [$new_views, $data["id"]]
        );
    }
}
*/

$upload_query = new UploadQuery($sb);

$uploads_by_author = $upload_query->query("RAND()", 20, "v.author = ? AND v.upload_id != ?", [$data["author"], $data["upload_id"]]);

$recommended_upload_array = [];

try {
    $recommended_upload_array = (new RecommendationQuery($database, $upload_query))->related($data, 20);
} catch (\Throwable $e) {
    error_log("RecommendationQuery failed: " . $e->getMessage());
}

if (!$recommended_upload_array) {
    try {
        $recommended_upload_array = (new LegacyRecommendationQuery($sb))->related($data, 20);
    } catch (\Throwable $e) {
        error_log("LegacyRecommendationQuery failed: " . $e->getMessage());
    }
}

// random uploads if neither works
if (!$recommended_upload_array) {
    $recommended_upload_array = $upload_query->query("RAND()", 20, "v.upload_id != ?", [$data["upload_id"]])->toCleanArray();
}

if ($sb->getSkinThemeOptions()["comments_load_at_page_load"] ?? false) {
    $comments = new CommentData($database, CommentLocation::Upload, $id);

    $comment_data = $comments->getComments();
    $comment_count = $comments->getCommentCount();
} else {
    $comment_data = [];
    $comment_count = 0;
}

$page_data = [
    "is_owner" => $owner,
    "int_id" => $data["id"],
    "id" => $data["upload_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["timestamp"],
    "original_site" => $data["original_site"],
    "published_originally" => $data["original_timestamp"],
    "type" => $data["type"],
    "file" => $data["upload_file"],
    "author" => [
        "id" => $data["author"],
        "info" => $author_info,
        "following" => $following,
    ],
    "interactions" => [
        "views" => $data["views"],
        "ratings" => $upload->getRatingData(),
        "favorites" => 0, // TODO
        "comments" => $comment_count,
    ],
    "comments" => $comment_data,
    "flags" => $flags,
    "rating" => $data["rating"],
    "recommended" => $recommended_upload_array,
    "tags" => $tags,
];

// if we are on bootstrap or on finalium 1, emulate the old like/dislike system.
if (Utilities::isClassicSkin()) {
    if ($auth->isLoggedIn()) {
        $current_rating_from_db = $database->result("SELECT rating FROM upload_ratings WHERE upload=? AND user=?", [$data["id"], $auth->getUserID()]);

        if (($current_rating_from_db == "4") || ($current_rating_from_db == "5")) {
            $current_rating = "like";
        } elseif (($current_rating_from_db == "1") || ($current_rating_from_db == "2")) {
            $current_rating = "dislike";
        } else {
            $current_rating = null;
        }
    } else {
        $current_rating = null;
    }

    $page_data["interactions"]["legacy"] = $upload->getRatingDataAsLikeRatio();
    $page_data["interactions"]["legacy"]["current_rating"] = $current_rating;
}

// this is kind of ugly. -chaziz 02/11/2026
$localization = $sb->getLocalizationClass();
$storage = $sb->getStorageClass();

$twig->setPageMeta([
    "opengraph_description" => (!empty(trim($data["description"])) ? $data["description"] : $localization->translate("no_description")),
    "opengraph_image" => $storage->getUploadThumbnail($data["id"], $data["type"], $flags["custom_thumbnail"]),
    "opengraph_type" => "article",
    "opengraph_published" => date("c", strtotime($data["timestamp"])),
    "opengraph_author" => Utilities::getURL(false) . "user/" . $author_info["username"],
    "metadata_author" => $author_info["username"],
    "opengraph_section" => $data["type"] == 2 ? "Image" : ($data["type"] == 3 ? "Music" : "Video")
]);

echo $twig->render('watch.twig', [
    'upload' => $page_data,
]);
