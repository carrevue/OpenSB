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

use BluffingoCore\Database;

/**
 * Authentication stuff.
 */
class Authentication
{
    private Database $database;
    private bool $is_logged_in = false;
    private int $user_id;
    private array $user_data;
    private $user_ban_data;
    private $user_stat_data;
    private $has_authenticated_as_staff = false;

    public function __construct(Database $database)
    {
        $accountfields = "id, ip, name, title, email, token, about, powerlevel, joined, lastview, birthdate, comfortable_rating, customcolor, blacklisted_tags, u_flags";
        $this->database = $database;
        $token = $_SESSION["SBTOKEN"] ?? null;

        if (isset($token)) {
            if ($this->user_id = $this->database->result("SELECT id FROM users WHERE token = ?", [$token])) {
                $this->is_logged_in = true;
                $this->user_data = $this->database->fetch("SELECT $accountfields FROM users WHERE id = ?", [$this->user_id]);
                $this->user_ban_data = $this->database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$this->user_id]);

                // moved from homepage
                $followers = $this->database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$this->user_id]);
                $views = $this->database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$this->user_id]);
                $notifications = $this->database->result("SELECT COUNT(*) FROM user_notifications WHERE recipient = ?", [$this->user_id]);

                $this->user_stat_data = [
                    "followers" => $followers,
                    "views" => $views ?? 0, // hacky fix otherwise on trinium if you have no views then it looks fucked
                    "notifications" => $notifications,
                ];
                // -------------------

                if (!isset($this->user_data['blacklisted_tags'])) {
                    $this->user_data['blacklisted_tags'] = [];
                } else {
                    $this->user_data['blacklisted_tags'] = json_decode($this->user_data['blacklisted_tags']); // decode this shit on the fly
                }

                // if the current logged-in user doesnt have a birthdate, redirect them to the
                // "specify your birthdate" verification page.
                if (!isset($this->user_data['birthdate'])) {
                    // dumbass hack, this is because we cant access the global $path variable
                    // set in /public/index.php since it probably hasnt been defined yet at this
                    // point in the code.
                    $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
                    $path = explode('/', $uri);
                    if ($path[1] != "verify_birthdate") {
                        header('Location: /verify_birthdate');
                        exit();
                    }
                }

                // if the user is banned/ip banned, instantly log them out.
                if ($this->user_ban_data || $this->database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [$this->user_data['ip']])) {
                    $this->logOut();
                }

                $database->query("UPDATE users SET lastview = ?, ip = ? WHERE id = ?", [time(), Utilities::getIpAddress(), $this->user_id]);

                // TODO: the content rating system is disabled on squarebracket, so if the user's "comfortable rating"
                // isnt general, then reset it back to general.

                // if "comfortable rating" is questionable, reset it back to general. this is because the site now uses
                // "general" and "sensitive" instead of the old "general", "questionable" and "mature" ratings, but the
                // old system is left there for compatibility. -chaziz 6/9/2024
                if ($this->user_data["comfortable_rating"] == "questionable") {
                    $this->database->query("UPDATE users SET comfortable_rating = 'general' WHERE id = ?", [$this->user_id]);
                    Utilities::notifyBanner("Your content filtering settings have been reset to General.", false, "primary");
                }

                if ($this->user_data["comfortable_rating"] == "mature" && !$this->isUserOver18()) {
                    $this->database->query("UPDATE users SET comfortable_rating = 'general' WHERE id = ?", [$this->user_id]);
                    Utilities::notifyBanner("Your content filtering settings have been reset to General.", false, "primary");
                }

                $this->has_authenticated_as_staff = $_SESSION["SB_STAFF_AUTHED"] ?? null;
            }
        }
    }

    /**
     * Logs out the user.
     */
    public function logOut(): never
    {
        session_destroy();
        Utilities::redirect('./');
    }

    /**
     * Checks if the user is logged in or not
     */
    public function isUserLoggedIn(): bool
    {
        return $this->is_logged_in;
    }

    /**
     * Returns only the user's id
     */
    public function getUserID(): ?int
    {
        return $this->is_logged_in ? $this->user_id : null;
    }

    /**
     * Returns user data
     */
    public function getUserData(): ?array
    {
        return $this->is_logged_in
            ? $this->user_data
            : [
                'comfortable_rating' => 'general',
                'blacklisted_tags' => [],
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
     * Returns user ban data if it exists.
     */
    public function getUserBanData(): ?array
    {
        return $this->user_ban_data ?: null;
    }

    /**
     * Checks if the logged-in user has at least the specified role level.
     */
    public function userHasRole(UserRoleEnum $role): bool
    {
        return $this->is_logged_in
            && ($this->user_data['powerlevel'] ?? UserRoleEnum::NoPermissions->value) >= $role->value;
    }

    /**
     * Checks if the logged-in user is an administrator (or of higher status).
     * 
     * @deprecated
     */
    public function isUserAdministrator(): bool
    {
        return $this->userHasRole(UserRoleEnum::Administrator);
    }

    /**
     * Checks if the logged-in user has authenticated as staff.
     */
    public function hasUserAuthenticatedAsStaff(): bool
    {
        return $this->is_logged_in
            && $this->has_authenticated_as_staff
            && ($this->user_data['powerlevel'] ?? UserRoleEnum::NoPermissions->value) > UserRoleEnum::Normal->value;
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
     * Returns the current user's list of flags.
     */
    public function getUserFlags($array = false)
    {
        if ($array) {
            return UserFlags::toArray($this->user_data['u_flags']);
        } else {
            return $this->user_data['u_flags'];
        }
    }

    /**
     * Checks if the logged-in user is over 18.
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
}
