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

global $twig, $database, $auth, $orange;

use Jaybizzle\CrawlerDetect\CrawlerDetect;
use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\UploadData;
use SquareBracket\UploadQuery;
use SquareBracket\UploadRatingEnum;
use SquareBracket\UserData;
use SquareBracket\UserRoleEnum;
use SquareBracket\Utilities;

$options = $orange->getLocalOptions();

$id = $path[2] ?? null;

if ($orange->isFulpTube()) {
    if (preg_match('/^MTY.*=\d{2}$/', subject: $id)) {
        Utilities::notifyBanner("This original FulpTube video no longer exists.", "/");
    }
}

$upload = new UploadData($database, $id);

// check if the upload has been taken down.
$takedown = $upload->getTakedown();
if ($takedown && !$auth->userHasRole(UserRoleEnum::Administrator)) {
    // go back to homepage with a notification
    Utilities::notifyBanner("This upload has been taken down.", "/");
}

if ($upload->isDeleted()) {
    Utilities::notifyBanner("This upload has been deleted.", "/");
}

$data = $upload->getData();
if (!$data) {
    Utilities::notifyBanner("This upload does not exist.", "/");
}

$tagBlacklist = $auth->getUserTagBlacklist();

if (isset($data["tags"])) {
    $decodedTags = json_decode($data["tags"]);
    if ($decodedTags !== null) {
        foreach ($decodedTags as $tag) {
            if (in_array($tag, $tagBlacklist)) {
                if ($auth->isUserLoggedIn()) {
                    Utilities::notifyBanner("This upload contains tags you've blacklisted.", "/");
                } else {
                    Utilities::notifyBanner("This upload contains tags blacklisted by default.", "/");
                }
            }
        }
    }
}

$author = new UserData($database, $data["author"]);

if ($author->isUserBanned() && !$auth->userHasRole(UserRoleEnum::Administrator)) {
    Utilities::notifyBanner("This upload has been taken down.", "/");
}

$tags = $upload->getTags();

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["author"]]);
$followed = Utilities::isFollowingUser($data["author"]);

// TODO: this feature is unused.
//$favorites = $database->result("SELECT COUNT(video_id) FROM user_favorites WHERE video_id=?", [$id]);

$flags = $upload->getUploadFlagsArray();

if ($flags["block_guests"] && !$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("Please login to view this upload.", "/login");
}

// this is awkward
$upload_rating = UploadRatingEnum::fromString($data["rating"]);
$comfortable_rating = UploadRatingEnum::fromString($auth->getUserData()["comfortable_rating"]);

if ($upload_rating->value > $comfortable_rating->value) {
    Utilities::notifyBanner("Access to mature-rated uploads is restricted.", "/");
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

    // add a limit of one guest view per 10 minutes on uploads. this is to deter potential viewbots from
    // quickly botting an upload's view count.
    if (
        !$auth->isUserLoggedIn() &&
        $database->result("SELECT COUNT(*) FROM upload_views WHERE video_id=? AND timestamp > 600", [$ip])
    ) {
        $ratelimit = true;
    }

    if (
        $database->result("SELECT COUNT(video_id) FROM upload_views WHERE video_id=? AND user=?", [$id, $ip]) < 1
        && !$ratelimit
    ) {
        $database->query(
            "INSERT INTO upload_views (video_id, user, timestamp, type) VALUES (?,?,?,?)",
            [$id, $ip, time(), $type]
        );

        // increment the indexed view count. this might go out of sync eventually, but this can be fixed through
        // 2024-08-recount-views.php.
        $new_views = $data["views"] + 1;
        $database->query(
            "UPDATE uploads SET views = ? WHERE id = ?",
            [$new_views, $data["id"]]
        );
    }
}

$whereRatings = Utilities::whereRatings();
$whereTagBlacklist = Utilities::whereTagBlacklist();
$submission_query = new UploadQuery($database);

// ported from poktwo, modified to accommodate for takedowns and relevancy.
$recommendfields = "
    jaccard.video_id,
    jaccard.flags,
    jaccard.intersect_count,
    jaccard.union_count,
    jaccard.intersect_count / jaccard.union_count AS jaccard_index
FROM
    (
    SELECT
        c2.video_id AS video_id,
        c2.flags AS flags,
        COUNT(ct2.tag_id) AS intersect_count,
        (
        SELECT
            COUNT(DISTINCT ct3.tag_id)
        FROM
            upload_tag_index ct3
        WHERE
            ct3.video_id IN (c1.id, c2.id)
    ) AS union_count
    FROM
        uploads AS c1
    INNER JOIN uploads AS c2
        ON c1.id != c2.id
    LEFT JOIN upload_tag_index AS ct1
        ON ct1.video_id = c1.id
    LEFT JOIN upload_tag_index AS ct2
        ON ct2.video_id = c2.id AND ct1.tag_id = ct2.tag_id
    WHERE
        c1.id = ?
        AND ct1.tag_id IS NOT NULL
        AND ct2.tag_id IS NOT NULL
    GROUP BY
        c2.video_id, c2.flags
    HAVING
        intersect_count > 0
    ) AS jaccard
WHERE
    jaccard.flags != 0x2
ORDER BY
    jaccard_index DESC
LIMIT 24";

$uploads_by_author = $submission_query->query("RAND()", 24, "v.author = ? AND v.video_id != ?", [$data["author"], $data["video_id"]]);

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
    ON v.video_id = recommended.video_id
    WHERE v.video_id NOT IN (SELECT submission FROM upload_takedowns)";

    if (!empty($whereRatings)) {
        $query .= "AND $whereRatings ";
    }

    if (!empty($twhereTagBlacklist)) {
        $query .= "AND $whereTagBlacklist ";
    }

    $query .= "AND v.author NOT IN (SELECT userid FROM user_bans)
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
    $random_uploads = $submission_query->query("RAND()", 24, "v.video_id != ?", [$data["video_id"]]);
    if ($random_uploads) {
        $random_uploads_array = Utilities::makeUploadArray($database, $random_uploads);
    } else {
        $random_uploads_array = [];
    }
} else {
    $random_uploads_array = [];
}

$owner = ($auth->getUserID() == $data["author"]);

if ($orange->getLocalOptions()["skin"] != "finalium") {
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
    "id" => $data["video_id"],
    "title" => $data["title"],
    "description" => $data["description"],
    "published" => $data["time"],
    "original_site" => $data["original_site"],
    "published_originally" => $data["original_time"],
    "type" => $data["post_type"],
    "file" => $data["videofile"],
    "author" => [
        "id" => $data["author"],
        "info" => $author->getUserArray(),
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
if (Utilities::isLegacyFrontend()) {
    if ($auth->isUserLoggedIn()) {
        $current_rating_from_db = $database->result("SELECT rating FROM upload_ratings WHERE video=? AND user=?", [$data["id"], $auth->getUserID()]);

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

    // translate 5 stars into like/dislikes. we do this because using the star rating ratio doesn't work that well
    // with the likesaber on finalium.
    // -chaziz 6/11/2024
    $ratings = $page_data["interactions"]["ratings"]["stars"];

    $likes = $ratings["4"] + $ratings["5"];
    $dislikes = $ratings["1"] + $ratings["2"];
    $total = $likes + $dislikes;

    // calculate finalium likesaber
    $ratio = ($total == 0 || $dislikes == 0)  ? 100
        : Utilities::calculatePercentage($dislikes, $likes, $total);

    $page_data["interactions"]["legacy"] = [
        "likes" => $likes,
        "dislikes" => $dislikes,
        "ratio" => $ratio,
        "current_rating" => $current_rating,
    ];
}

// TODO: this should be moved to admin_upload_edit -chaziz 1/4/2025
if ($auth->userHasRole(UserRoleEnum::Administrator) && $takedown) {
    $page_data["takedown"] = $takedown[0];
    $page_data["takedown"]["takedownee"] = Utilities::userIDToUsername($database, $takedown[0]["sender"]);
    $page_data["author_banned"] = $author->isUserBanned();
} else {
    $page_data["takedown"] = [];
    $page_data["author_banned"] = false;
}

echo $twig->render('watch.twig', [
    'submission' => $page_data,
]);
