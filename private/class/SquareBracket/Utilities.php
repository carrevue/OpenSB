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

namespace SquareBracket;

use DateTime;
use Exception;
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
            $flags = UploadFlags::toArray($upload["flags"]);

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
        global $orange;

        $journalsData = [];
        foreach ($journals as $journal) {
            if ($orange->isFulpTube() && $journal["is_site_news"]) {
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
        global $orange;

        if ($orange->getAuthenticationClass()->isUserLoggedIn()) {
            $rating = $orange->getAuthenticationClass()->getUserData()["comfortable_rating"];

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

    public static function whereTagBlacklist(): string
    {
        global $orange;

        $tagBlacklist = $orange->getAuthenticationClass()->getUserTagBlacklist();

        // we use old-fashioned json tags instead of the "new" ported-from-poktwo tags so we don't have to bloat
        // submission-related queries into 20 fucking useless lines that slows the site down to a crawl.
        // -chaziz 6/23/2024
        $conditions = [];
        foreach ($tagBlacklist as $tag) {
            $conditions[] = "JSON_CONTAINS(v.tags, '\"$tag\"') = 0";
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Not to be confused with notifyBanner, which makes a banner.
     */
    public static function notifyUser($database, $user, $location, $related_id, NotificationEnum $type): void
    {
        global $orange, $database;

        if (!$orange->getAuthenticationClass()->isUserLoggedIn()) {
            throw new Exception("NotifyUser should not be called if the current user is logged off.");
        }

        $dontNotify = false;

        // dont bother notifying someone if someones re-following them in less than a week of the first time they
        // followed that user
        if ($type == NotificationEnum::Follow) {
            if ($database->result("SELECT COUNT(*) FROM user_notifications WHERE timestamp > ? AND type = ?
                AND recipient = ? AND sender = ?", [time() - 604800, $type->value, $user, $orange->getAuthenticationClass()->getUserID()])) {
                $dontNotify = true;
            }
        }

        if (!$dontNotify) {
            // Notify the user
            $database->query(
                "INSERT INTO user_notifications (type, level, recipient, sender, timestamp, related_id) VALUES (?,?,?,?,?,?);",
                [$type->value, $location, $user, $orange->getAuthenticationClass()->getUserID(), time(), $related_id]
            );
        }
    }

    public static function isFollowingUser($user)
    {
        global $orange, $database;

        return $database->result("SELECT COUNT(user) FROM user_follows WHERE id=? AND user=?", [$user, $orange->getAuthenticationClass()->getUserID()]);
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

    /**
     * @throws \DateMalformedStringException
     */
    public static function calculateAge($birthdate): int
    {
        $birthDate = new DateTime($birthdate);
        $today = new DateTime('now');
        $interval = $today->diff($birthDate);
        return $interval->y;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public static function calculateAgeFrom($birthdate, $date): int
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

    // if you're using cloudflare, make sure you've properly configured your server so ips arent cloudflare ips.
    public static function getIpAddress($encrypted = true)
    {
        if (BLUFF_CLI) return null;

        $ip = $_SERVER['REMOTE_ADDR'];

        if ($ip == "127.0.0.1" | $ip == "::1" | $ip == "localhost") return "localhost";

        if ($encrypted) {
            return crypt($ip, $ip);
        } else {
            return $ip;
        }
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
}
