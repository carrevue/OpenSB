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

namespace OpenSB\Pages;

global $twig, $database, $auth, $sb;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use OpenSB\CommentData;
use OpenSB\CommentLocation;
use OpenSB\UploadData;
use OpenSB\UploadQuery;
use OpenSB\UserRoleEnum;
use OpenSB\Utilities;
use OpenSB\UploadVisibilityEnum;

$options = $sb->getLocalOptions();

function handle_error(string $message, string $redirect = "/") {
    global $sb, $twig; // Lol

    if ($sb->isHitchhiker()) {
        echo $twig->render('watch_error.twig', [
            'error' => $message,
        ]);
        die();
    } else {
        // go back to homepage with a notification (or something else if specified)
        Utilities::notifyBanner($message, $redirect);
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

$upload = new UploadData($database, $id);

// check if the upload has been taken down.
if ($upload->isTakenDown() && !$auth->userHasRole(UserRoleEnum::Moderator)) {
    // go back to homepage with a notification
    Utilities::notifyBanner("notify_taken_down_upload", "/");
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
                if ($auth->isUserLoggedIn()) {
                    handle_error("notify_upload_tag_blacklist_logged_in");
                } else {
                    handle_error("notify_upload_tag_blacklist_logged_out");
                }
            }
        }
    }
}

$author_info = $upload->getAuthorData();

/*
if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    if ($author_info["flags"]["unverified"] || !$owner) {
        Utilities::notifyBanner("notify_upload_unverified", "/");
    }
}
*/

$tags = $upload->getTags();

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["author"]]);
$followed = Utilities::isFollowingUser($data["author"]);

// TODO: this feature is unused.
//$favorites = $database->result("SELECT COUNT(upload_id) FROM user_favorites WHERE upload_id=?", [$id]);

$flags = $upload->getFlagArray();

if ($flags["block_guests"] && !$auth->isUserLoggedIn()) {
    handle_error("notify_login_required_view_upload", "/login");
}

if ($flags["mature"] && !$auth->getUserFlags(true)["mature_content_access"]) {
    handle_error("notify_cannot_access_mature_upload");
}

$ip = Utilities::getIpAddress();

$CrawlerDetect = new CrawlerDetect;

$type = $auth->isUserLoggedIn() ? "user" : "guest";

// probably shit
if (!$CrawlerDetect->isCrawler()) {
    $ratelimit = false;

    // add a limit of one view per minute on guests. this is to deter other forms of crawlers/bots that may
    // not be properly caught by crawlerdetect.
    if (
        !$auth->isUserLoggedIn() &&
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

// loose shitty recommendations
if ($options["use_new_recommendation_shit"] ?? false) {
    $recommendfields = "
    jaccard.upload_id,
    jaccard.flags,
    jaccard.intersect_count,
    jaccard.union_count,
    jaccard.intersect_count / jaccard.union_count AS jaccard_index
    FROM (
        SELECT
            c2.upload_id AS upload_id,
            c2.flags AS flags,
            COUNT(ct2.tag_id) AS intersect_count,
            (
                c1_tagcount.c1_count +
                c2_tagcount.c2_count -
                COUNT(ct2.tag_id)
            ) AS union_count
        FROM uploads AS c1
        JOIN upload_tag_index AS ct1
            ON ct1.upload_id = c1.id
        JOIN upload_tag_index AS ct2
            ON ct2.tag_id = ct1.tag_id
        JOIN uploads AS c2
            ON c2.id = ct2.upload_id AND c2.id != c1.id

        JOIN (
            SELECT upload_id, COUNT(*) AS c1_count
            FROM upload_tag_index
            GROUP BY upload_id
        ) AS c1_tagcount ON c1_tagcount.upload_id = c1.id

        JOIN (
            SELECT upload_id, COUNT(*) AS c2_count
            FROM upload_tag_index
            GROUP BY upload_id
        ) AS c2_tagcount ON c2_tagcount.upload_id = c2.id

        WHERE c1.id = ?

        GROUP BY
            c2.upload_id,
            c2.flags,
            c1_tagcount.c1_count,
            c2_tagcount.c2_count
        HAVING intersect_count > 0
    ) AS jaccard
    WHERE jaccard.flags != 0x2
    ORDER BY jaccard_index DESC
    LIMIT 24";
} else {
    $recommendfields = "
        jaccard.upload_id,
        jaccard.flags,
        jaccard.intersect_count,
        jaccard.union_count,
        jaccard.intersect_count / jaccard.union_count AS jaccard_index
    FROM
        (
        SELECT
            c2.upload_id AS upload_id,
            c2.flags AS flags,
            COUNT(ct2.tag_id) AS intersect_count,
            (
            SELECT
                COUNT(DISTINCT ct3.tag_id)
            FROM
                upload_tag_index ct3
            WHERE
                ct3.upload_id IN (c1.id, c2.id)
        ) AS union_count
        FROM
            uploads AS c1
        INNER JOIN uploads AS c2
            ON c1.id != c2.id
        LEFT JOIN upload_tag_index AS ct1
            ON ct1.upload_id = c1.id
        LEFT JOIN upload_tag_index AS ct2
            ON ct2.upload_id = c2.id AND ct1.tag_id = ct2.tag_id
        WHERE
            c1.id = ?
            AND ct1.tag_id IS NOT NULL
            AND ct2.tag_id IS NOT NULL
        GROUP BY
            c2.upload_id, c2.flags
        HAVING
            intersect_count > 0
        ) AS jaccard
    WHERE
        jaccard.flags != 0x2
    ORDER BY
        jaccard_index DESC
    LIMIT 24";
}

$uploads_by_author = $upload_query->query("RAND()", 24, "v.author = ? AND v.upload_id != ?", [$data["author"], $data["upload_id"]]);

if ($tags === []) {
    // if there are no tags, list the author's other uploads
    $recommended = false;
} else {
    // if there are tags, use jaccard stuff ported from poktwo to list uploads that may be relevant enough.
    // this isn't ported to UploadQuery for now, as it will require me to rework all of UploadQuery.

    $query = "SELECT v.* 
    FROM uploads v
    INNER JOIN (
        SELECT $recommendfields
    ) AS recommended
    ON v.upload_id = recommended.upload_id
    WHERE v.upload_id NOT IN (SELECT upload FROM upload_takedowns)";

    if (!empty($whereRatings)) {
        $query .= "AND $whereRatings ";
    }

    if (!empty($twhereTagBlacklist)) {
        $query .= "AND $whereTagBlacklist ";
    }

    $query .= "AND v.author NOT IN (SELECT user FROM user_bans)
    ORDER BY RAND()";

    $recommended = $database->fetchArray($database->query($query, [$data["id"]]));

    // if no other uploads match, then fallback to listing the author's other uploads
    if (empty($recommended)) {
        $recommended = false;
    }
}

if ($recommended) {
    $recommended_upload_array = Utilities::makeUploadArray($database, $recommended);
} else {
    $recommended_upload_array = [];
}

if ($uploads_by_author) {
    $uploads_by_author_array = Utilities::makeUploadArray($database, $uploads_by_author);
} else {
    $uploads_by_author_array = [];
}

if (!$recommended && !$uploads_by_author) {
    $random_uploads = $upload_query->query("RAND()", 24, "v.upload_id != ?", [$data["upload_id"]]);
    if ($random_uploads) {
        $random_uploads_array = Utilities::makeUploadArray($database, $random_uploads);
    } else {
        $random_uploads_array = [];
    }
} else {
    $random_uploads_array = [];
}

if ($sb->getLocalOptions()["skin"] != "finalium") {
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
        "followers" => $followers,
        "following" => $followed,
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
    if ($auth->isUserLoggedIn()) {
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
    "opengraph_description" => (!empty(trim($data["description"])) ? $data["description"] : $localization->translate("upload_no_description")),
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
