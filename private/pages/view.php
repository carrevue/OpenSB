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
use Data\Upload\UploadFlags;
use Data\User\UserFlags;
use Data\Upload\UploadResult;

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

function recommendation_string_similarity(?string $a, ?string $b): float {
    if (empty($a) || empty($b)) return 0.0;
    $clean = fn(string $s) => mb_strtolower(preg_replace('/[\p{P}\p{S}]/u', '', $s));
    $tokenize = fn(string $s) => array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($s))));

    $wordsA = array_values($tokenize($clean($a)));
    $wordsB = array_values($tokenize($clean($b)));

    if (count($wordsA) === 0 || count($wordsB) === 0) return 0.0;

    $intersection = count(array_intersect($wordsA, $wordsB));
    $union        = count(array_unique(array_merge($wordsA, $wordsB)));

    return $union === 0 ? 0.0 : $intersection / $union;
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

$whereRatings = $auth->databaseWhereRatingsHelper();
$whereTagBlacklist = $auth->databaseWhereTagBlacklistHelper();
$upload_query = new UploadQuery($sb);

$uploads_by_author = $upload_query->query("RAND()", 20, "v.author = ? AND v.upload_id != ?", [$data["author"], $data["upload_id"]]);

// this isn't ported to UploadQuery for now, as it will require me to rework all of UploadQuery.
$candidateQuery = "
    SELECT v.*, u.flags AS author_flags, u.last_seen
    FROM uploads v
    JOIN users u ON u.id = v.author
    WHERE v.upload_id != ?
    AND v.flags != " . UploadFlags::FLAG_UNPROCESSED->value . "
    AND v.visibility = " . UploadVisibilityEnum::Public->value . "
    AND v.upload_id NOT IN (SELECT upload FROM upload_takedowns)
    AND v.author NOT IN (SELECT user FROM user_bans)
";

$candidateParams = [$data["upload_id"]];

if (!empty($whereRatings)) {
    $candidateQuery .= " AND $whereRatings";
}

if (!empty($whereTagBlacklist['sql'])) {
    $candidateQuery .= " AND " . $whereTagBlacklist['sql'];
    // tag blacklist ?'s come after the upload_id ? in the query string,
    // so params must be appended in the same order -chaziz 07/16/2026
    $candidateParams = array_merge($candidateParams, $whereTagBlacklist['params']);
}

$candidates = $database->fetchArray(
    $database->query($candidateQuery, $candidateParams)
);

// get the tags
$sourceTags = array_column(
    $database->fetchArray($database->query(
        "SELECT tag_id FROM upload_tag_index WHERE upload_id = ?",
        [$data["id"]]
    )),
    'tag_id'
);

$allTags = [];
if (!empty($candidates)) {
    $candidateIds = array_column($candidates, 'id');
    $placeholders = implode(',', array_fill(0, count($candidateIds), '?'));
    $tagRows = $database->fetchArray($database->query(
        "SELECT upload_id, tag_id FROM upload_tag_index WHERE upload_id IN ($placeholders)",
        $candidateIds
    ));
    foreach ($tagRows as $row) {
        $allTags[$row["upload_id"]][] = $row["tag_id"];
    }
}

// avoid recommending masturbatory uploads and dramaslop.
// this should *probably* be moved to settings
$recommendation_title_penality = [
    // websites
    'squarebracket', 
    'opensb', 
    'fulptube', 
    'subrocks', 
    'poktube', 
    'vidlii', 
    'bitview', 
    'vlare', 
    'betacast', 
    'eracast', 
    'kamtape',
    'goodtube',
    'rehike',
];

// now score this shit
if (!empty($candidates)) {
    $sourceTagCount = count($sourceTags);
    // count uploads per author across all candidates
    $authorUploadCounts = array_count_values(array_column($candidates, 'author'));
    foreach ($candidates as &$row) {
        $candidateTags = $allTags[$row["id"]] ?? [];
        $intersection  = count(array_intersect($sourceTags, $candidateTags));
        $union         = $sourceTagCount + count($candidateTags) - $intersection;
        $jaccard       = $union > 0 ? $intersection / $union : 0.0;

        $titleLower = strtolower($row["title"]);
        $penalty    = 0.0;

        foreach ($recommendation_title_penality as $word) {
            if (str_contains($titleLower, $word)) {
                $penalty += 1;
            }
        }

        if ($row["author"] !== $data["author"]) {
            $authorCount = $authorUploadCounts[$row["author"]] ?? 0;

            // if the author hasn't logged in over a year, penalize.
           if ($row["last_seen"] < strtotime('-1 year')) {
                $penalty = 10;
            }

            // if the author has one upload, penalize.
            if ($authorCount == 1) {
                $penalty = 50;
            }

            // if the author is shadow banned, just penalize that shit completely.
            if ($row["author_flags"] & UserFlags::FLAG_SHADOW_BAN->value) {
                $penalty = 100;
            }
        }

        $row["relevance_score"] =
            (
                ($jaccard * 0.75) +
                (recommendation_string_similarity($data["title"], $row["title"]) * 0.1) +
                (recommendation_string_similarity($data["description"] ?? '', $row["description"] ?? '') * 0.05)
            )
            - $penalty;
    }
    unset($row);

    usort($candidates, fn($a, $b) => $b["relevance_score"] <=> $a["relevance_score"]);

    $relevant = array_values(array_filter($candidates, fn($row) => $row["relevance_score"] > 0.005));

    shuffle($relevant);
    $recommended = array_slice($relevant, 0, 20);
    $recommended = !empty($recommended) ? $recommended : false;
}

if ($recommended) {
    $recommended_upload_array = (new UploadResult($database, $recommended))->toCleanArray();
} else {
    $recommended_upload_array = [];
}

if ($uploads_by_author) {
    $uploads_by_author_array = $uploads_by_author->toCleanArray();
} else {
    $uploads_by_author_array = [];
}

// fallback in case recommended somehow doesnt work and the author has no other uploads
if (!$recommended && !$uploads_by_author) {
    $random_uploads = $upload_query->query("RAND()", 20, "v.upload_id != ?", [$data["upload_id"]]);
    if ($random_uploads) {
        $random_uploads_array = $random_uploads->toCleanArray();
    } else {
        $random_uploads_array = [];
    }
} else {
    $random_uploads_array = [];
}

if ($sb->getCurrentSkinName() != "finalium") {
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
    "other_by_author" => $uploads_by_author_array,
    "random" => $random_uploads_array,
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

// TODO: this should be moved to admin_upload_edit -chaziz 1/4/2025
/*
if ($auth->userHasRole(UserRoleEnum::Administrator) && $takedown) {
    $page_data["takedown"] = $takedown[0];
    $page_data["takedown"]["takedownee"] = Utilities::userIDToUsername($database, $takedown[0]["sender"]);
    $page_data["author_banned"] = $author->isUserBanned();
} else {
    $page_data["takedown"] = [];
    $page_data["author_banned"] = false;
}
*/

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
