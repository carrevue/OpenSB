<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

namespace Core;

use Data\Account\AccountFlags;
use Data\User\UserRoleEnum;
use Data\User\UserFlags;

/**
 * Authentication stuff.
 */
class Authentication
{
    private Database $database;
    private bool $is_logged_in = false;
    private int $user_id;
    private int $account_id;
    private array|false $account_data;
    private array|false $user_data;
    private $user_ban_data;
    private $user_stat_data;
    private $has_authenticated_as_staff = false;
    private string $cookie_warning_string = "DO-NOT-SHARE-THIS-WITH-ANYONE-";

    public function __construct(Database $database)
    {
        $this->database = $database;

        if (isset($_COOKIE["SBAUTH"])) {
            $cookie_raw = $_COOKIE["SBAUTH"];

            // get rid of warning string
            if (str_starts_with($cookie_raw, $this->cookie_warning_string)) {
                $cookie_raw = substr($cookie_raw, strlen($this->cookie_warning_string));
            } else {
                return;
            }

            $decoded = Utilities::verifySignedCookiePayload($cookie_raw);

            if ($decoded !== false && is_array($decoded)) {
                $active = $decoded;
            } else {
                return;
            }
        } else {
            return;
        }

        $user_fields = [
            "id", 
            "ip", 
            "name", 
            "title", 
            "email", 
            "token",
            "about", 
            "powerlevel", 
            "joined", 
            "last_seen", 
            "birthdate", 
            "comfortable_rating", 
            "userlink_color", 
            "blacklisted_tags",
            "flags",
            "f_index",
            "u_index",
        ];

        if (isset($active["token"])) {
            $fields = implode(", ", $user_fields);

            $this->account_data = $this->database->fetch("SELECT * FROM accounts WHERE token = ?", [$active["token"]]);
            $this->account_id = $this->account_data["id"];

            $role_row = $this->database->fetch(
                "SELECT role FROM account_user_roles WHERE account = ? AND user = ?",
                [$this->account_data['id'], $active["user_id"]]
            );

            if (!$role_row) {
                return; // user doesn't belong to this account
            }

            $this->user_data = $this->database->fetch("SELECT $fields FROM users WHERE id = ?", [$active["user_id"]]);

            if ($this->user_data) {
                $this->is_logged_in = true;
                $this->user_id = $this->user_data["id"];
                $this->user_ban_data = $this->database->fetch("SELECT * FROM user_bans WHERE user = ?", [$this->user_id]);

                $views = $this->database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$this->user_id]);
                $notifications = $this->database->result("SELECT COUNT(*) FROM user_notifications WHERE recipient = ?", [$this->user_id]);

                $this->user_stat_data = [
                    "followers" => $this->user_data["f_index"] ?? 0,
                    "views" => $views ?? 0,
                    "notifications" => $notifications ?? 0,
                ];

                if (!isset($this->user_data['blacklisted_tags'])) {
                    $this->user_data['blacklisted_tags'] = [];
                } else {
                    $this->user_data['blacklisted_tags'] = json_decode($this->user_data['blacklisted_tags']);
                }

                // if the current logged-in user doesnt have a birthdate, redirect them to the
                // "specify your birthdate" verification page.
                if (!isset($this->user_data['birthdate']) && !$this->isBanned()) {
                    // dumbass hack, this is because we cant access the global $path variable
                    // set in /public/index.php since it probably hasnt been defined yet at this
                    // point in the code.
                    $path = Utilities::getPathAsArray();
                    if ($path[1] != "verify_birthdate") {
                        Utilities::redirect("/verify_birthdate");
                    }
                }

                $this->database->query("UPDATE users SET ip = ? WHERE id = ?", [Utilities::getIpAddress(), $this->user_id]);

                // if "comfortable rating" is questionable, reset it back to general. this is because opensb now uses
                // "general" and "sensitive" instead of the old "general", "questionable" and "mature" ratings, but the
                // old system is left there for compatibility, which will probably get removed around opensb 2.1.
                // -chaziz 09/19/2025
                if ($this->user_data["comfortable_rating"] == "questionable" ||
                    ($this->user_data["comfortable_rating"] != "general" && !$this->isUserOver18())) 
                {
                    $this->database->query("UPDATE users SET comfortable_rating = 'general' WHERE id = ?", [$this->user_id]);
                    Utilities::notifyBanner("notify_content_filtering_reset", false, "accent");
                }

                $this->has_authenticated_as_staff = $_SESSION["SB_STAFF_AUTHED"] ?? null;
            }
        }
    }

    /**
     * function getWarningString
     *
     * Returns warning string for auth cookie.
     *
     * @return string
     */
    public function getWarningString(): string
    {
        return $this->cookie_warning_string;
    }

    public function bumpLastActive()
    {
        $this->database->query("UPDATE users SET last_seen = ? WHERE id = ?", [time(), $this->user_id]);
        $this->database->query("UPDATE accounts SET last_login = ? WHERE id = ?", [time(), $this->account_id]);
    }

    /**
     * Logs out.
     */
    public function logOut(): void
    {
        session_destroy();
        setcookie("SBAUTH", "", time() - 3600);
        Utilities::redirect('./');
    }

    /**
     * Returns if logged in or not.
     */
    public function isLoggedIn(): bool
    {
        return $this->is_logged_in;
    }

    /**
     * Returns only the current user's id
     */
    public function getAccountID(): ?int
    {
        return $this->is_logged_in ? $this->account_id : null;
    }

    /**
     * Returns only the current account's id
     */
    public function getUserID(): ?int
    {
        return $this->is_logged_in ? $this->user_id : null;
    }

    /**
     * Returns current account data
     */
    public function getAccountData(): ?array
    {
        return $this->is_logged_in
            ? $this->account_data
            : [];
    }

    /**
     * Returns current user data
     */
    public function getUserData(): ?array
    {
        return $this->is_logged_in
            ? $this->user_data
            : [
                'comfortable_rating' => 'general',
                'blacklisted_tags' => [],
                'flags' => 0,
            ];
    }

    /**
     * Returns user statistic data (views, followers) if it exists
     */
    public function getUserStatData(): array
    {
        return $this->is_logged_in ? $this->user_stat_data : [];
    }

    /**
     * Returns if the user is banned.
     */
    public function isBanned(): bool
    {
        return !empty($this->user_ban_data);
    }

    /**
     * Returns user ban data.
     */
    public function getUserBanData(): array
    {
        return $this->user_ban_data ?? [];
    }

    /**
     * Checks if the logged-in user has at least the specified role level.
     */
    public function userHasRole(UserRoleEnum $role): bool
    {
        return $this->is_logged_in
            && ($this->user_data['powerlevel'] ?? UserRoleEnum::None->value) >= $role->value;
    }

    /**
     * Checks if the logged-in user has authenticated as staff.
     */
    public function hasUserAuthenticatedAsStaff(): bool
    {
        return $this->is_logged_in
            && $this->has_authenticated_as_staff
            && ($this->user_data['powerlevel'] ?? UserRoleEnum::None->value) > UserRoleEnum::Normal->value;
    }

    /**
     * Returns the user's tag blacklist.
     */
    public function getUserTagBlacklist(): array
    {
        if (!$this->is_logged_in) {
            return [];
        }

        return $this->user_data['blacklisted_tags'] ?? [];
    }

    /**
     * Returns the current user's flags.
     */
    public function getUserFlags($array = false)
    {
        if ($array) {
            return UserFlags::toArray($this->user_data['flags']);
        } else {
            return $this->user_data['flags'];
        }
    }

    /**
     * Returns the current account's flags.
     */
    public function getAccountFlags($array = false)
    {
        if ($array) {
            return AccountFlags::toArray($this->account_data['flags']);
        } else {
            return $this->account_data['flags'];
        }
    }

    /**
     * Checks if the logged-in user is over 18.
     * 
     * TODO: port this over to accounts
     */
    public function isUserOver18(): bool
    {
        if ($this->is_logged_in) {
            $age = date_diff(date_create($this->user_data['birthdate']), date_create('today'))->y;

            return $age >= 18;
        } else {
            return false;
        }
    }

    /**
     * Get all users from an account.
     */
    public function getUsersFromAccount(): array
    {
        if (!$this->account_data) return [];

        return $this->database->fetchArray($this->database->query(
            "SELECT u.id, u.name, u.title, u.f_index
             FROM users u
             JOIN account_user_roles r ON r.user = u.id
             WHERE r.account = ?",
            [$this->account_data['id']]
        ));
    }

    /**
     * Database helper for the user's comfortable rating.
     */
    public function databaseWhereRatingsHelper(): string
    {
        if ($this->isLoggedIn()) {
            $rating = $this->user_data["comfortable_rating"];

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

    /**
     * Database helper for the user's tag blacklist.
     */
    public function databaseWhereTagBlacklistHelper(): array
    {
        $tagBlacklist = $this->getUserTagBlacklist();

        // we use old-fashioned json tags instead of the "new" ported-from-poktwo tags so we don't have to bloat
        // upload-related queries into 20 fucking useless lines that slows the site down to a crawl.
        // -chaziz 6/23/2024
        $conditions = [];
        $params = [];
        foreach ($tagBlacklist as $tag) {
            $conditions[] = "JSON_CONTAINS(v.tags, JSON_QUOTE(?)) = 0";
            $params[] = $tag;
        }

        return [
            'sql' => implode(' AND ', $conditions),
            'params' => $params,
        ];
    }
}