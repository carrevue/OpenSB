<?php

namespace SquareBracket;

use DateTime;

use JetBrains\PhpStorm\NoReturn;
use Random\Randomizer;

/**
 * Static utilities.
 */
class Utilities
{
    // TODO: i think this should be refactored into UploadQuery? i should look into this later. -chaziz 1/4/2025
    public static function makeUploadArray($database, $uploads): array
    {
        if (!$uploads) return [];

        $uploadArray = [];
        foreach ($uploads as $upload) {

            $flags = Utilities::uploadBitmaskToArray($upload["flags"]);

            $ratings = new UploadRatingData($database, $upload["id"]);

            $userData = new UserData($database, $upload["author"]);
            $uploadArray[] =
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
                    "flags" => $flags,
                    "length" => $upload["videolength"],
                    "author" => [
                        "id" => $upload["author"],
                        "info" => $userData->getUserArray(),
                    ],
                    "interactions" => [
                        "ratings" => $ratings->calculateRatingData(),
                    ],
                ];
        }

        return $uploadArray;
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
        
        $tagBlacklist = $auth->getUserTagBlacklist();

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
    public static function ratingToNumber($rating): int
    {
        return match ($rating) {
            'general' => 0,
            'questionable' => 1, // completely unused
            'mature' => 2,
        };
    }

    /**
     * Not to be confused with notifyBanner, which makes a banner.
     */
    public static function notifyUser($database, $user, $location, $related_id, NotificationEnum $type): void
    {
        global $auth, $database;

        if (!$auth->isUserLoggedIn()) {
            throw new CoreException("NotifyUser should not be called if the current user is logged off.");
        }

        $dontNotify = false;

        // dont bother notifying someone if someones re-following them in less than a week of the first time they
        // followed that user
        if ($type == NotificationEnum::Follow) {
            if ($database->result("SELECT COUNT(*) FROM user_notifications WHERE timestamp > ? AND type = ?
                AND recipient = ? AND sender = ?", [time() - 604800, $type->value, $user, $auth->getUserID()])) {
                $dontNotify = true;
            }
        }

        if (!$dontNotify) {
            // Notify the user
            $database->query("INSERT INTO user_notifications (type, level, recipient, sender, timestamp, related_id) VALUES (?,?,?,?,?,?);",
                [$type->value, $location, $user, $auth->getUserID(), time(), $related_id]);
        }
    }

    public static function isFollowingUser($user) {
        global $auth, $database;

        return $database->result("SELECT COUNT(user) FROM user_follows WHERE id=? AND user=?", [$user, $auth->getUserID()]);
    }

    public static function uploadBitmaskToArray($bitmask): array
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
     * Notifies the current user with a banner.
     *
     * This is not to be confused with NotifyUser, which is for the (still incomplete as of now)
     * notifications system.
     *
     * @param $message
     * @param $redirect
     * @param string $color
     */
    public static function notifyBanner($message, $redirect, string $color = "danger"): void
    {
        $_SESSION["notif_message"] = $message;
        $_SESSION["notif_color"] = $color;

        if ($redirect) {
            header(sprintf('Location: %s', $redirect));
            die();
        }
    }

    #[NoReturn] public static function redirect($url, ...$args): void
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

    public static function usernameToUserID($database, $username)
    {
        if ($data = $database->fetch("SELECT id FROM users WHERE name = ?", [$username])) {
            return $data["id"];
        } else {
            return false;
        }
    }

    public static function userIDToUsername($database, $id)
    {
        if ($data = $database->fetch("SELECT name FROM users WHERE id = ?", [$id])) {
            return $data["name"];
        } else {
            return false;
        }
    }

    public static function uploadStringIDToUploadNumericID($database, $uploadStringID)
    {
        if ($data = $database->fetch("SELECT id FROM uploads WHERE video_id = ?", [$uploadStringID])) {
            return $data["id"];
        } else {
            return false;
        }
    }

    public static function uploadNumericIDToUploadStringID($database, $uploadNumericID)
    {
        if ($data = $database->fetch("SELECT video_id FROM uploads WHERE id = ?", [$uploadNumericID])) {
            return $data["video_id"];
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

    public static function validateUsername($username, $database, $checkIfTaken = true): string
    {
        $error = "";

        if (!isset($username)) $error .= "This username is blank. ";
        if ($checkIfTaken) {
            if ($database->result("SELECT COUNT(*) FROM users WHERE name = ?", [$username])) $error .= "This username has already been taken. ";
        }
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $username)) $error .= "This username contains invalid characters. ";

        // TODO: add blacklist for usernames
        if ($username == "news") $error .= "This is an invalid username. ";

        return $error;
    }

    public static function convertBytes($value, $decimals = 0)
    {
        if (is_numeric($value)) {
            $bytes = $value;
        } else {
            $value_length = strlen($value);
            $qty = substr($value, 0, $value_length - 1);
            $unit = strtolower(substr($value, $value_length - 1));

            switch ($unit) {
                case 'k':
                    $bytes = $qty * 1024;
                    break;
                case 'm':
                    $bytes = $qty * 1048576; // 1024^2
                    break;
                case 'g':
                    $bytes = $qty * 1073741824; // 1024^3
                    break;
                case 't':
                    $bytes = $qty * 1099511627776; // 1024^4
                    break;
                case 'p':
                    $bytes = $qty * 1125899906842624; // 1024^5
                    break;
                default:
                    $bytes = $value;
                    break;
            }
        }

        $sz = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $factor = floor((strlen($bytes) > 1 ? log($bytes, 1024) : 0));
        $factor = min($factor, count($sz) - 1);

        $converted = $bytes / pow(1024, $factor);

        return sprintf("%.{$decimals}f %s", $converted, $sz[$factor]);
    }

    // if you're using cloudflare, make sure you've properly configured your server so ips arent cloudflare ips.
    public static function getIpAddress($encrypted = true)
    {
        if (SB_CLI) return null;

        $ip = $_SERVER['REMOTE_ADDR'];

        if ($ip == "127.0.0.1" | $ip == "::1" | $ip == "localhost") return "localhost";

        if ($encrypted) {
            return crypt($ip, $ip);
        } else {
            return $ip;
        }
    }

    // ok so $stupidFuckingHack exists because $debugFulpTube may not be fully initalized if this gets called
    // too early. maybe this function should just be moved into the core SquareBracket class? -chaziz 5/7/2025
    public static function isFulpTube($stupidFuckingHack = false)
    {
        global $isChazizSB, $orange, $isDebug;

        $debugFulpTube = $orange?->getLocalOptions()["debug_fulptube_branding"] ?? false;

        if ($stupidFuckingHack) { $debugFulpTube = true; }

        // bypass logic completely if we're debugging fulptube branding.
        if ($debugFulpTube && $isDebug) {
            return true;
        }

        return ($isChazizSB) && isset($_SERVER['HTTP_HOST']) && ($_SERVER['HTTP_HOST'] == 'fulptube.rocks');
    }

    public static function isLegacyFrontend()
    {
        global $orange;

        $localOptions = $orange?->getLocalOptions();

        return ($localOptions["skin"] == "finalium" || $localOptions["skin"] == "bootstrap");
    }

    public static function calculatePercentage($number, $percent, $total): float|int
    {
        // if the upload has no "dislikes", return 100.
        if ($total == 0 or $number == 0) {
            return 100;
        } else {
            // return the like-to-dislike ratio.
            return ($percent / $total) * $number * 100;
        }
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
            'fulptube.me' => 'squarebracket.me', // yeah its owned by sks but lets keep this here
            'fulptube.pw' => 'squarebracket.pw',
            'fulptube.veselcraft.ru' => 'squarebracket.veselcraft.ru', // this domain still works lol
        ];

        $output = str_replace(array_keys($urlReplacements), array_values($urlReplacements), $output);

        // now replace all *actual* squarebracket urls with fulptube.rocks
        $properUrlReplacements = [
            '://squarebracket.pw' => '://fulptube.rocks',
            '://squarebracket.veselcraft.ru' => '://fulptube.rocks',
        ];

        $output = str_replace(array_keys($properUrlReplacements), array_values($properUrlReplacements), $output);

        return $output;
    }

    public static function logOutUser() {
        session_destroy();
        Utilities::redirect('./');
    }
}
