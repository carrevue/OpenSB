<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2025 Chaziz

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

use DateTime;
use Exception;
use Random\Randomizer;

/**
 * class Utilities
 *
 * Static utilities.
 */
class Utilities
{
    public static function getURL(bool $includeURI = false): ?string
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return null;
        }

        $protocol = self::isThisHttps() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        if ($includeURI && isset($_SERVER['REQUEST_URI'])) {
            return $protocol . '://' . $host . $_SERVER['REQUEST_URI'];
        }

        return $protocol . '://' . $host;
    }

    public static function redirect(string $url, int $statusCode = 302): never
    {
        header("Location: $url", true, $statusCode);
        exit;
    }

    public static function isThisHttps()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }

        // somewhat inefficient?
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cf_visitor = json_decode($_SERVER['HTTP_CF_VISITOR']);
            if (isset($cf_visitor->scheme) && $cf_visitor->scheme === 'https') {
                return true;
            }
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        return false;
    }

    /**
     * function makeUploadArray
     * 
     * @todo i think this should be refactored into UploadQuery? i should look into this later. -chaziz 1/4/2025
     *
     * @param mixed $database
     * @param mixed $uploads
     *
     * @return array
     */
    public static function makeUploadArray($database, $uploads): array
    {
        if (!$uploads) return [];

        $uploadArray = [];
        foreach ($uploads as $upload) {
            $flags = UploadFlags::toArray($upload["flags"]);

            $ratings = new UploadRatingData($database, $upload["id"]);

            $userData = new UserData($database, $upload["author"]);
            $uploadArray[] =
                [
                    "id" => $upload["upload_id"],
                    "title" => $upload["title"],
                    "description" => $upload["description"],
                    "published" => $upload["timestamp"],
                    "published_originally" => $upload["original_timestamp"],
                    "original_site" => $upload["original_site"],
                    "type" => $upload["type"],
                    "content_rating" => $upload["rating"],
                    "views" => $upload["views"],
                    "flags" => $flags,
                    "length" => $upload["video_length"],
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

    /**
     * function makeJournalArray
     *
     * @param mixed $database
     * @param mixed $journals
     *
     * @return array
     */
    public static function makeJournalArray($database, $journals): array
    {
        global $sb;

        $journalsData = [];
        foreach ($journals as $journal) {
            if ($sb->isFulpTube() && $journal["is_news"]) {
                $journal["title"] = self::replaceSquareBracketWithFulpTube($journal["title"]);
                $journal["post"] = self::replaceSquareBracketWithFulpTube($journal["post"]);
            }

            $userData = new UserData($database, $journal["author"]);
            $journalsData[] =
                [
                    "id" => $journal["id"],
                    "title" => $journal["title"],
                    "contents" => $journal["post"],
                    "published" => $journal["timestamp"],
                    "author" => [
                        "id" => $journal["author"],
                        "info" => $userData->getUserArray(),
                    ],
                ];
        }

        return $journalsData;
    }

    /**
     * function notifyUser
     *
     * Not to be confused with notifyBanner, which makes a banner.
     *
     * @param mixed $database
     * @param mixed $user
     * @param mixed $location
     * @param mixed $related_id
     * @param NotificationEnum $type
     *
     * @return void
     */
    public static function notifyUser($database, $user, $location, $related_id, NotificationEnum $type): void
    {
        global $sb, $database;

        if (!$sb->getAuthenticationClass()->isUserLoggedIn()) {
            throw new Exception("NotifyUser should not be called if the current user is logged off.");
        }

        $dontNotify = false;

        // dont bother notifying someone if someones re-following them in less than a week of the first time they
        // followed that user
        if ($type == NotificationEnum::Follow) {
            if ($database->result("SELECT COUNT(*) FROM user_notifications WHERE timestamp > ? AND type = ?
                AND recipient = ? AND sender = ?", [time() - 604800, $type->value, $user, $sb->getAuthenticationClass()->getUserID()])) {
                $dontNotify = true;
            }
        }

        if (!$dontNotify) {
            // Notify the user
            $database->query(
                "INSERT INTO user_notifications (type, level, recipient, sender, timestamp, related_id) VALUES (?,?,?,?,?,?);",
                [$type->value, $location, $user, $sb->getAuthenticationClass()->getUserID(), time(), $related_id]
            );
        }
    }

    /**
     * function isFollowingUser
     *
     * @param mixed $user
     *
     * @return mixed
     */
    public static function isFollowingUser($user)
    {
        global $sb, $database;

        return $database->result("SELECT COUNT(user) FROM user_follows WHERE id=? AND user=?", [$user, $sb->getAuthenticationClass()->getUserID()]);
    }

    /**
     * function notifyBanner
     *
     * Notifies the current user with a banner.
     * This is not to be confused with NotifyUser, which is for the (still incomplete as of now)
     * notifications system.
     *
     * @param mixed $message
     * @param mixed $redirect
     * @param string $color
     * @param array $args
     *
     * @return void
     */
    public static function notifyBanner($message, $redirect, string $color = "danger", array $args = []): void
    {
        global $sb;

        $localization = $sb->getLocalizationClass();

        // awkward fix for if we use notifyBanner before localization is initialized
        if (!$localization) {
            $localization = new Localization($sb->getOptionsCookie()["locale"] ?? "en-US");
        }

        $_SESSION["notif_message"] = $localization->translate($message, ...$args);
        $_SESSION["notif_color"] = $color;

        if ($redirect) {
            // this should most definitely use redirect
            header(sprintf('Location: %s', $redirect));
            die();
        }
    }

    /**
     * function generateRandomString
     *
     * @param mixed $length
     * @param mixed $includeSymbols
     *
     * @return string
     */
    public static function generateRandomString($length, $includeSymbols = false): string
    {
        if ($includeSymbols) {
            $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-";
        } else {
            $string = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        }

        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $new = substr(str_shuffle($string), 0, $length);
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

    /**
     * function usernameToUserID
     *
     * @param mixed $database
     * @param mixed $username
     *
     * @return mixed|bool
     */
    public static function usernameToUserID($database, $username)
    {
        if ($data = $database->fetch("SELECT id FROM users WHERE name = ?", [$username])) {
            return $data["id"];
        } else {
            return false;
        }
    }

    /**
     * function userIDToUsername
     *
     * @param mixed $database
     * @param mixed $id
     *
     * @return mixed|bool
     */
    public static function userIDToUsername($database, $id)
    {
        if ($data = $database->fetch("SELECT name FROM users WHERE id = ?", [$id])) {
            return $data["name"];
        } else {
            return false;
        }
    }

    /**
     * function uploadStringIDToUploadNumericID
     *
     * @param mixed $database
     * @param mixed $uploadStringID
     *
     * @return mixed|bool
     */
    public static function uploadStringIDToUploadNumericID($database, $uploadStringID)
    {
        if ($data = $database->fetch("SELECT id FROM uploads WHERE upload_id = ?", [$uploadStringID])) {
            return $data["id"];
        } else {
            return false;
        }
    }

    /**
     * function uploadNumericIDToUploadStringID
     *
     * @param mixed $database
     * @param mixed $uploadNumericID
     *
     * @return mixed|bool
     */
    public static function uploadNumericIDToUploadStringID($database, $uploadNumericID)
    {
        if ($data = $database->fetch("SELECT upload_id FROM uploads WHERE id = ?", [$uploadNumericID])) {
            return $data["upload_id"];
        } else {
            return false;
        }
    }

    /**
     * function uploadStringIDToUploadTitle
     *
     * @param mixed $database
     * @param mixed $uploadStringID
     *
     * @return mixed|bool
     *
     * @todo this should probably be merged with the functions above because imho this is kinda fuckin ugly -chaziz 7/24/2025
     */
    public static function uploadStringIDToUploadTitle($database, $uploadStringID)
    {
        if ($data = $database->fetch("SELECT title FROM uploads WHERE upload_id = ?", [$uploadStringID])) {
            return $data["title"];
        } else {
            return false;
        }
    }

    /**
     * function uploadNumericIDToUploadTitle
     *
     * @param mixed $database
     * @param mixed $uploadNumericID
     *
     * @return mixed|bool
     */
    public static function uploadNumericIDToUploadTitle($database, $uploadNumericID)
    {
        if ($data = $database->fetch("SELECT title FROM uploads WHERE id = ?", [$uploadNumericID])) {
            return $data["title"];
        } else {
            return false;
        }
    }

    /**
     * function journalIDtoJournalTitle
     *
     * @param mixed $database
     * @param mixed $journalID
     *
     * @return mixed|bool
     */
    public static function journalIDtoJournalTitle($database, $journalID)
    {
        if ($data = $database->fetch("SELECT title FROM journals WHERE id = ?", [$journalID])) {
            return $data["title"];
        } else {
            return false;
        }
    }

    /**
     * function calculateAge
     *
     * @param mixed $birthdate
     *
     * @return int
     */
    public static function calculateAge($birthdate): int
    {
        $birthDate = new DateTime($birthdate);
        $today = new DateTime('now');
        $interval = $today->diff($birthDate);
        return $interval->y;
    }

    /**
     * function calculateAgeFrom
     *
     * @param mixed $birthdate
     * @param mixed $date
     *
     * @return int
     */
    public static function calculateAgeFrom($birthdate, $date): int
    {
        $birthDate = new DateTime($birthdate);
        $targetDate = new DateTime();
        $targetDate->setTimestamp($date);
        $interval = $targetDate->diff($birthDate);
        return $interval->y;
    }

    /**
     * function validateUsername
     *
     * @param mixed $username
     * @param mixed $database
     * @param mixed $checkIfTaken
     *
     * @return string
     */
    public static function validateUsername($username, $database, $checkIfTaken = true): string
    {
        $error = "";

        // TODO: redo these errors in a way that theyre localizable with notifyBanner
        if (!isset($username)) $error .= "This username is blank. ";
        if ($checkIfTaken) {
            if ($database->result("SELECT COUNT(*) FROM users WHERE name = ?", [$username])) $error .= "This username has already been taken. ";
        }
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $username)) $error .= "This username contains invalid characters. ";

        // TODO: add blacklist for usernames. for a somewhat crude blacklist, 
        // you may use the user_old_names table and point the username you wish
        // to blacklist to an invalid id (i'd recommend 0). a proper blacklist
        // will be added in opensb 2.1. -chaziz 11/19/2025
        if ($username == "news") $error .= "Invalid username. ";
        if ($username == "InvalidUser!") $error .= "Invalid username. ";
        if (str_starts_with($username, "DummyAccount-")) $error .= "Invalid username. ";

        return $error;
    }

    /**
     * function formatBytes
     *
     * @param mixed $value
     * @param mixed $decimals
     *
     * @return mixed
     */
    public static function formatBytes($value, $decimals = 0)
    {
        if (is_numeric($value)) {
            $bytes = $value;
        } else {
            $value_length = strlen($value);
            $qty = substr($value, 0, $value_length - 1);
            $unit = strtolower(substr($value, $value_length - 1));

            $bytes = match ($unit) {
                'k' => $qty * 1024,
                'm' => $qty * 1048576,
                'g' => $qty * 1073741824,
                't' => $qty * 1099511627776,
                'p' => $qty * 1125899906842624,
                default => $value,
            };
        }

        $sz = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $factor = floor((strlen($bytes) > 1 ? log($bytes, 1024) : 0));
        $factor = min($factor, count($sz) - 1);

        $converted = $bytes / pow(1024, $factor);

        return sprintf("%.{$decimals}f %s", $converted, $sz[$factor]);
    }

    /**
     * function getIpAddress
     * 
     * if you're using cloudflare, make sure you've properly configured your server so ips arent cloudflare ips.
     *
     * @return mixed|string
     */
    public static function getIpAddress()
    {
        if (BLUFF_CLI) return null;

        $ip = $_SERVER['REMOTE_ADDR'];

        if ($ip == "127.0.0.1" | $ip == "::1" | $ip == "localhost") return "localhost";

        return $ip;
    }

    /**
     * function isLegacyFrontend
     *
     * @return mixed
     */
    public static function isLegacyFrontend()
    {
        global $sb;

        $localOptions = $sb?->getLocalOptions();

        return ($localOptions["skin"] == "finalium" || $localOptions["skin"] == "bootstrap");
    }

    /**
     * function calculatePercentage
     *
     * @param float $number
     * @param float $percent
     * @param float $total
     *
     * @return string
     */
    public static function calculatePercentage(float $number, float $percent, float $total): string
    {
        return $total == 0 ? '0%' : number_format(($percent / $total) * $number * 100, 2) . '%';
    }

    /**
     * function replaceSquareBracketWithFulpTube
     *
     * @param mixed $input
     *
     * @return mixed
     */
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
            'fulptube.me' => 'squarebracket.pw',
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

    /**
     * function adjustCssColorBrightness
     *
     * @param mixed $hex
     * @param mixed $percent
     *
     * @return string
     */
    private static function adjustCssColorBrightness($hex, $percent): string
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

    // calculate the color used for profile banner on the bootstrap frontend
    // the original implementation for this used a scss php compiler library 
    // thing but that is fucking stupid and it'll slow down the site, so lets
    // just approximate this.

    /**
     * function makeBootstrapFrontendProfileGradient
     *
     * @param mixed $userlink_color
     *
     * @return mixed
     */
    public static function makeBootstrapFrontendProfileGradient($userlink_color)
    {
        // approximate bootstrap's text-contrast scss function
        $hex = ltrim($userlink_color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $colorBrightness = round(($r * 299 + $g * 587 + $b * 114) / 1000);
        $textColor = ($colorBrightness < 130) ? 'white' : 'black'; // 255/2 ≈ 130

        // generate the gradient colors
        $gradientStart = Utilities::adjustCssColorBrightness($userlink_color, 0);
        $gradientMid = Utilities::adjustCssColorBrightness($userlink_color, -7);
        $gradientEnd = Utilities::adjustCssColorBrightness($userlink_color, -15);

        $primaryStart = Utilities::adjustCssColorBrightness($userlink_color, 8);
        $primaryMid = $userlink_color;
        $primaryEnd = Utilities::adjustCssColorBrightness($userlink_color, -4);

        // now turn this into css (yes, this is fucking ugly)
        return "
        .bg-custom-profile {
            background-image: linear-gradient({$gradientStart}, {$gradientMid} 50%, {$gradientEnd});
            color: {$textColor};
        }

        .bg-primary {
            background-image: linear-gradient({$primaryStart}, {$primaryMid} 60%, {$primaryEnd});
        }
        ";
    }

    // (hmac-sha256)
    private static function getCookieSecret()
    {
        // the env should be set to a long, random value in production
        $env = getenv('APP_SECRET');
        if (!empty($env)) return $env;

        // fallback: derive from server name, php version and file path (not secure but its fine for dev)
        $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        return hash('sha256', $serverName . '|' . PHP_VERSION . '|' . __FILE__);
    }

    public static function makeSignedCookiePayload(array $data): string
    {
        $payload = base64_encode(json_encode($data));
        $sig = hash_hmac('sha256', $payload, self::getCookieSecret());
        return $sig . ':' . $payload;
    }

    public static function verifySignedCookiePayload(string $signed)
    {
        if (strpos($signed, ':') === false) return false;
        list($sig, $payload) = explode(':', $signed, 2);
        $expected = hash_hmac('sha256', $payload, self::getCookieSecret());
        if (!hash_equals($expected, $sig)) return false;
        $decoded = json_decode(base64_decode($payload), true);
        return is_array($decoded) ? $decoded : false;
    }

    public static function setSafeCookie(string $name, string $value, int $expire = 0)
    {
        $secure = self::isThisHttps();
        setcookie($name, $value, [
            'expires' => $expire,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
