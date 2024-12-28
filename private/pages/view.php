<?php

namespace OpenSB;

global $twig, $database, $auth, $orange;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

use SquareBracket\CommentData;
use SquareBracket\CommentLocation;
use SquareBracket\UploadData;
use SquareBracket\UploadQuery;
use SquareBracket\Utilities;
use SquareBracket\UserData;

$id = $path[2] ?? null;

$submission = new UploadData($database, $id);

// check if the upload has been taken down.
$takedown = $submission->getTakedown();
if ($takedown && !$auth->isUserAdmin()) {
    // go back to homepage with a notification
    Utilities::bannerNotification("This upload has been taken down.", "/");
}

if ($submission->isDeleted()) {
    Utilities::bannerNotification("This upload has been deleted.", "/");
}

$data = $submission->getData();
if (!$data) {
    Utilities::bannerNotification("This upload does not exist.", "/");
}

$tagBlacklist = $auth->getUserBlacklistedTags();

if (isset($data["tags"])) {
    $decodedTags = json_decode($data["tags"]);
    if ($decodedTags !== null) {
        foreach ($decodedTags as $tag) {
            if (in_array($tag, $tagBlacklist)) {
                if ($auth->isUserLoggedIn()) {
                    Utilities::bannerNotification("This upload is blacklisted per your settings.", "/");
                } else {
                    Utilities::bannerNotification("This upload is blacklisted by default.", "/");
                }
            }
        }
    }
}


$comments = new CommentData($database, CommentLocation::Upload, $id);
$author = new UserData($database, $data["author"]);
if ($author->isUserBanned() && !$auth->isUserAdmin()) {
    Utilities::bannerNotification("The author of this upload is banned.", "/");
}

$tags = $submission->getTags();

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["author"]]);
$followed = Utilities::IsFollowingUser($data["author"]);

// looks weird, whatever.
$ratings = [
    "1" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=1", [$data["id"]]),
    "2" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=2", [$data["id"]]),
    "3" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=3", [$data["id"]]),
    "4" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=4", [$data["id"]]),
    "5" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=5", [$data["id"]]),
];
$favorites = $database->result("SELECT COUNT(video_id) FROM user_favorites WHERE video_id=?", [$id]);

$bools = $submission->bitmaskToArray();

if ($bools["block_guests"] && !$auth->isUserLoggedIn())
{
    Utilities::bannerNotification("The author of this upload has blocked guest access.", "/login");
}

if (Utilities::RatingToNumber($data["rating"]) > Utilities::RatingToNumber($auth->getUserData()["comfortable_rating"])) {
    Utilities::bannerNotification("Access to mature-rated uploads is restricted.", "/");
}

$ip = Utilities::getIpAddress();

$CrawlerDetect = new CrawlerDetect;

if ($auth->isUserLoggedIn()) {
    $type = "user";
} else {
    $type = "guest";
}


// stupid fucking check
function domainCheck()
{
    global $isDebug, $isChazizSB;

    if ($isDebug) { return true; }

    $allowedChazizSbDomains = ['squarebracket.pw', 'fulptube.rocks', 'squarebracket.bluffingo.net'];
    $currentDomain = $_SERVER['HTTP_HOST'];

    if ($isChazizSB) {
        if (in_array($currentDomain, $allowedChazizSbDomains)) {
            return true;
        } else {
            return false;
        }
    } else {
        return true;
    }
}

// probably shit
if (!$CrawlerDetect->isCrawler() && domainCheck()) {
    if ($database->fetch("SELECT COUNT(video_id) FROM upload_views WHERE video_id=? AND user=?", [$id, $ip])['COUNT(video_id)'] < 1) {
        $database->query("INSERT INTO upload_views (video_id, user, timestamp, type) VALUES (?,?,?,?)",
            [$id, $ip, time(), $type]);

        // increment the indexed view count. this might go out of sync eventually, but this can be fixed with a
        // script that'll be run at least once a week via cron. -chaziz 4/6/2024
        $new_views = $data["views"] + 1;
        $database->query("UPDATE uploads SET views = ? WHERE id = ?",
            [$new_views, $data["id"]]);
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
    // this isn't ported to UploadQuery for now since this query uses a slightly different syntax.

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


if ($auth->getUserID() == $data["author"]) { $owner = true; } else { $owner = false; }

$comment_data = $comments->getComments();
$comment_count = $comments->getCommentCount();

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
    "file" => Utilities::getUploadFile($data),
    "author" => [
        "id" => $data["author"],
        "info" => $author->getUserArray(),
        "followers" => $followers,
        "following" => $followed,
    ],
    "interactions" => [
        "views" => $data["views"],
        "ratings" => Utilities::calculateUploadRatings($ratings),
        "favorites" => $favorites,
        "comments" => $comment_count,
    ],
    "comments" => $comment_data,
    "bools" => $bools,
    "rating" => $data["rating"],
    "recommended" => $recommended_upload_array,
    "other_by_author" => $uploads_by_author_array,
    "random" => $random_uploads_array,
    "tags" => $tags,
];

// if were on bootstrap or finalium 1, replicate like/dislike system.
if ($orange->getLocalOptions()["skin"] == "finalium" || $orange->getLocalOptions()["skin"] == "bootstrap") {
    // calculates the ratio for the likesaber
    function calculateRatio($number, $percent, $total): float|int
    {
        // if there's no ratio or dislikes, return 100.
        if ($total == 0 or $number == 0) {
            return 100;
        } else {
            // return the Like-to-dislike ratio.
            return ($percent / $total) * $number * 100;
        }
    }

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
    // with the likesaber on finalium. TODO: bring back like/dislike system onto bootstrap
    // -chaziz 6/11/2024
    $likes = $ratings["4"] + $ratings["5"];
    $dislikes = $ratings["1"] + $ratings["2"];
    $total = $likes + $dislikes;

    $page_data["interactions"]["legacy"] = [
        "likes" => $likes,
        "dislikes" => $dislikes,
        "ratio" => calculateRatio($dislikes, $likes, $total),
        "current_rating" => $current_rating,
    ];
}

if ($auth->isUserAdmin() && $takedown) {
    $page_data["takedown"] = $takedown[0];
    $page_data["takedown"]["takedownee"] = Utilities::idToUsername($database, $takedown[0]["sender"]);
    $page_data["author_banned"] = $author->isUserBanned();
} else {
    $page_data["takedown"] = [];
    $page_data["author_banned"] = false;
}

echo $twig->render('watch.twig', [
    'submission' => $page_data,
]);