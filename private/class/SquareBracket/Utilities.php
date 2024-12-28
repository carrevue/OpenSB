<?php

namespace SquareBracket;

use DateTime;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use JetBrains\PhpStorm\NoReturn;
use Random\Randomizer;

/**
 * Static utilities.
 */
class Utilities
{
    /**
     * Get the upload's file, works for all three storage modes.
     *
     * @param array $submission The upload data
     * @return array|string|null
     */
    public static function getUploadFile(array $submission): array|string|null
    {
        if ($submission == null)
        {
            return null;
        }

        return $submission['videofile'];
    }

    /**
     * Calculate the upload's ratings.
     */
    public static function calculateUploadRatings($ratings): array
    {
        $total_ratings = ($ratings["1"] +
            $ratings["2"] +
            $ratings["3"] +
            $ratings["4"] +
            $ratings["5"]);

        if ($total_ratings == 0) {
            $average_ratings = 0;
        } else {
            $average_ratings = ($ratings["1"] +
                    $ratings["2"] * 2 +
                    $ratings["3"] * 3 +
                    $ratings["4"] * 4 +
                    $ratings["5"] * 5) / $total_ratings;
        }

        return [
            "stars" => $ratings,
            "total" => $total_ratings,
            "average" => $average_ratings,
        ];
    }

    public static function makeUploadArray($database, $uploads): array
    {
        $submissionsData = [];
        foreach ($uploads as $upload) {

            $bools = Utilities::submissionBitmaskToArray($upload["flags"]);

            $ratingData = [
                "1" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=1", [$upload["id"]]),
                "2" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=2", [$upload["id"]]),
                "3" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=3", [$upload["id"]]),
                "4" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=4", [$upload["id"]]),
                "5" => $database->result("SELECT COUNT(rating) FROM upload_ratings WHERE video=? AND rating=5", [$upload["id"]]),
            ];

            $userData = new UserData($database, $upload["author"]);
            $submissionsData[] =
                [
                    "id" => $upload["video_id"],
                    "title" => $upload["title"],
                    "description" => $upload["description"],
                    "published" => $upload["time"],
                    "published_originally" => $upload["original_time"],
                    "original_site" => $upload["original_site"],
                    "type" => $upload["post_type"],
                    "content_rating" => $upload["rating"],
                    "views" => $upload["views"],
                    "flags" => $bools,
                    "author" => [
                        "id" => $upload["author"],
                        "info" => $userData->getUserArray(),
                    ],
                    "interactions" => [
                        "ratings" => Utilities::calculateUploadRatings($ratingData),
                    ],
                ];
        }

        return $submissionsData;
    }

    public static function makeJournalArray($database, $journals): array
    {
        $journalsData = [];
        foreach ($journals as $journal) {
            if (self::isFulpTube() && $journal["is_site_news"]) {
                $journal["title"] = self::replaceSquareBracketWithFulpTube($journal["title"]);
                $journal["post"] = self::replaceSquareBracketWithFulpTube($journal["post"]);
            }

            $userData = new UserData($database, $journal["author"]);
            $journalsData[] =
                [
                    "id" => $journal["id"],
                    "title" => $journal["title"],
                    "contents" => $journal["post"],
                    "published" => $journal["date"],
                    "author" => [
                        "id" => $journal["author"],
                        "info" => $userData->getUserArray(),
                    ],
                ];
        }

        return $journalsData;
    }

    public static function whereRatings(): string
    {
        global $auth;

        if ($auth->isUserLoggedIn()) {
            $rating = $auth->getUserData()["comfortable_rating"];

            $return_value = match ($rating) {
                'general' => 'v.rating IN ("general")',
                'questionable' => 'v.rating IN ("general","questionable")', // unused
                'mature' => 'v.rating IN ("general","questionable","mature")',
            };
        } else {
            $return_value = 'v.rating IN ("general")';
        }

        return $return_value;
    }

    public static function whereTagBlacklist(): string {
        global $auth;
        
        $tagBlacklist = $auth->getUserBlacklistedTags();

        // we use old-fashioned json tags instead of the "new" ported-from-poktwo tags so we don't have to bloat
        // submission-related queries into 20 fucking useless lines that slows the site down to a crawl.
        // -chaziz 6/23/2024
        $conditions = [];
        foreach ($tagBlacklist as $tag) {
            $conditions[] = "JSON_CONTAINS(v.tags, '\"$tag\"') = 0";
        }

        return implode(' AND ', $conditions);
    }

    // TODO: This should probably be an enum class.
    public static function RatingToNumber($rating): int
    {
        return match ($rating) {
            'general' => 0,
            'questionable' => 1, // completely unused
            'mature' => 2,
        };
    }

    /**
     * Not to be confused with Notification, which makes a banner.
     */
    public static function NotifyUser($database, $user, $submission, $related_id, NotificationEnum $type): void
    {
        global $auth, $database;

        if (!$auth->isUserLoggedIn()) {
            throw new CoreException("NotifyUser should not be called by the backend if current user is logged off.");
        }

        // If this user hasn't been notified by an identical notification the day prior.
        if (!$database->result("SELECT COUNT(*) FROM user_notifications WHERE timestamp > ? AND type = ? AND recipient = ? AND sender = ?",
                [time() - 86400, $type->value, $user, $auth->getUserID()])) {
            // Notify the user
            $database->query("INSERT INTO user_notifications (type, level, recipient, sender, timestamp, related_id) VALUES (?,?,?,?,?,?);",
                [$type->value, $submission, $user, $auth->getUserID(), time(), $related_id]);
        }
    }

    public static function IsFollowingUser($user) {
        global $auth, $database;

        return $database->result("SELECT COUNT(user) FROM user_follows WHERE id=? AND user=?", [$user, $auth->getUserID()]);
    }

    public static function submissionBitmaskToArray($bitmask): array
    {
        return [
            "featured" => (bool)($bitmask & 1),
            "unprocessed" => (bool)($bitmask & 2),
            "block_guests" => (bool)($bitmask & 4),
            "block_comments" => (bool)($bitmask & 8),
            "custom_thumbnail" => (bool)($bitmask & 16),
        ];
    }

    /**
     * Notifies the user, VidLii-style.
     *
     * Not to be confused with NotifyUser.
     *
     * @param $message
     * @param $redirect
     * @param string $color
     */
    public static function bannerNotification($message, $redirect, string $color = "danger"): void
    {
        $_SESSION["notif_message"] = $message;
        $_SESSION["notif_color"] = $color;

        if ($redirect) {
            header(sprintf('Location: %s', $redirect));
            die();
        }
    }


    public static function processImageUploadFile($temp_name, $target): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(4096);
        $img->toPng()->save($target);
    }


    public static function processImageUploadThumbnail($temp_name, $target): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(240); // used to be 500, but 500 was too big when the site displays thumbnails smaller than that.
        $img->toJpeg(90)->save($target);
    }


    public static function processCustomUploadThumbnail($temp_name, $target): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(1280);
        $img->toJpeg(80)->save($target);
    }


    public static function processProfilePicture($temp_name, $target): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        // i have to do this otherwise non-1:1 images that are smaller than 512x512 won't be stretched
        $img->resize(512, 512);
        $img->toPng()->save($target);
    }

    public static function processProfileBanner($temp_name, $target): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->resizeDown(height: 323);
        $img->toPng()->save($target);
    }

    public static function rewritePHP(): void
    {
        if (str_contains($_SERVER["REQUEST_URI"], '.php')) {
            $newUrl = str_replace('.php', '', $_SERVER["REQUEST_URI"]);
            header('Location: ' . $newUrl, true, 301);
            die();
        }
    }

    #[NoReturn] public static function redirect($url, ...$args)
    {
        header('Location: ' . sprintf($url, ...$args));
        die();
    }

    public static function generateRandomString($length, $includeSymbols = false): string
    {
        if ($includeSymbols) {
            $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-";
        } else {
            $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }

        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $new = substr(str_shuffle($string),0,$length);
        } else {
            // this feels cleaner imho
            $randomizer = new Randomizer();
            $new = $randomizer->getBytesFromString(
                $string,
                $length,
            );
        }

        return $new;
    }

    public static function usernameToID($database, $username)
    {
        if ($data = $database->fetch("SELECT id FROM users WHERE name = ?", [$username])) {
            return $data["id"];
        } else {
            return false;
        }
    }

    public static function idToUsername($database, $id)
    {
        if ($data = $database->fetch("SELECT name FROM users WHERE id = ?", [$id])) {
            return $data["name"];
        } else {
            return false;
        }
    }

    public static function calculateAge($birthdate)
    {
        $birthDate = new DateTime($birthdate);
        $today = new DateTime('now');
        $interval = $today->diff($birthDate);
        return $interval->y;
    }

    public static function calculateAgeFrom($birthdate, $date)
    {
        $birthDate = new DateTime($birthdate);
        $date_fuck = new DateTime();
        $date_fuck->setTimestamp($date);
        $interval = $date_fuck->diff($birthDate);
        return $interval->y;
    }

    public static function validateUsername($username, $database, $checkIfTaken = true) {
        $error = "";

        if (!isset($username)) $error .= "This username is blank. ";
        if ($checkIfTaken) {
            if ($database->result("SELECT COUNT(*) FROM users WHERE name = ?", [$username])) $error .= "This username has already been taken. ";
        }
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $username)) $error .= "This username contains invalid characters. ";

        return $error;
    }

    public static function convertBytes($value, $decimals = 0)
    {
        if (is_numeric($value)) {
            return $value;
        } else {
            $value_length = strlen($value);
            $qty = substr($value, 0, $value_length - 1);
            $unit = strtolower(substr($value, $value_length - 1));
            switch ($unit) {
                case 'k':
                    $qty *= 1024;
                    break;
                case 'm':
                    $qty *= 1048576;
                    break;
                case 'g':
                    $qty *= 1073741824;
                    break;
            }
        }
        $sz = 'BKMGTP';
        $factor = floor((strlen($qty) - 1) / 3);
        return sprintf("%.{$decimals}f", $qty / pow(1024, $factor)) . @$sz[$factor];
    }

    // if you're using cloudflare, make sure you've properly configured your server so ips arent cloudflare ips.
    public static function getIpAddress()
    {
        if (SB_CLI) return null;

        $ip = $_SERVER['REMOTE_ADDR'];

        if ($ip == "127.0.0.1" | $ip == "::1") return "localhost";

        return crypt($ip, $ip);
    }

    public static function isFulpTube()
    {
        global $isChazizSB, $orange, $isDebug;

        $debugFulpTube = $orange->getLocalOptions()["debug_fulptube_branding"] ?? false;

        // bypass logic completely if we're debugging fulptube branding.
        if ($debugFulpTube && $isDebug) {
            return true;
        }

        return ($isChazizSB) && isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'fulptube.rocks');
    }

    public static function replaceSquareBracketWithFulpTube($input)
    {
        // replace "squarebracket" with "fulptube"
        $replacements = [
            'squarebracket' => 'fulptube',
            'squareBracket' => 'FulpTube',
            'SquareBracket' => 'FulpTube',
            'SQUAREBRACKET' => 'FULPTUBE',
        ];

        $output = str_replace(array_keys($replacements), array_values($replacements), $input);

        // de-fuck urls
        $urlReplacements = [
            'fulptube.me' => 'squarebracket.me',
            'fulptube.pw' => 'squarebracket.pw',
            'fulptube.veselcraft.ru' => 'squarebracket.veselcraft.ru', // this domain still works lol
        ];

        $output = str_replace(array_keys($urlReplacements), array_values($urlReplacements), $output);

        // now replace all *actual* squarebracket urls with fulptube.rocks
        $properUrlReplacements = [
            '://squarebracket.me' => '://fulptube.rocks',
            '://squarebracket.pw' => '://fulptube.rocks',
            '://squarebracket.veselcraft.ru' => '://fulptube.rocks',
            '://sb.billyisreal.com' => '://fulptube.rocks',
        ];

        $output = str_replace(array_keys($properUrlReplacements), array_values($properUrlReplacements), $output);

        return $output;
    }
}
