<?php

namespace SquareBracket;

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
    // TODO: make this default blacklist configurable per instance
    private $default_tags_blacklist = [];
    private $has_authenticated_as_an_admin = false;

    public function __construct(Database $database)
    {
        $accountfields = "id, ip, name, title, email, title, about, powerlevel, joined, lastview, birthdate, comfortable_rating, customcolor, blacklisted_tags, token";
        $this->database = $database;
        $token = $_SESSION["SBTOKEN"] ?? null;

        if (isset($token)) {
            if($this->user_id = $this->database->result("SELECT id FROM users WHERE token = ?", [$token])) {
                $this->is_logged_in = true;
                $this->user_data = $this->database->fetch("SELECT $accountfields FROM users WHERE id = ?", [$this->user_id]);
                $this->user_ban_data = $this->database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$this->user_id]);

                // moved from homepage
                $followers = $this->database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$this->user_id]);
                $views = $this->database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$this->user_id]);
                $notifications = $this->database->result("SELECT COUNT(*) FROM user_notifications WHERE recipient = ?", [$this->user_id]);

                $this->user_stat_data = [
                    "followers" => $followers,
                    "views" => $views,
                    "notifications" => $notifications,
                ];
                // -------------------

                if (!isset($this->user_data['blacklisted_tags'])) {
                    $this->user_data['blacklisted_tags'] = $this->default_tags_blacklist;
                } else {
                    $this->user_data['blacklisted_tags'] = json_decode($this->user_data['blacklisted_tags']); // decode this shit on the fly
                }

                if (!isset($this->user_data['birthdate'])) {
                    $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
                    $path = explode('/', $uri);
                    if ($path[1] != "verify_birthdate") {
                        header('Location: /verify_birthdate');
                        exit();
                    }
                }

                // check if the current logged-in user is IP banned from another address, if so, then log them out.
                // this will prevent users from using IP banned accounts on other IPs.
                if ($this->database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [$this->user_data['ip']])) {
                    session_destroy();
                    Utilities::redirect('./');
                }

                // update "last logged in" timestamp after 12 hours.
                if ($database->result("SELECT COUNT(*) FROM users WHERE lastview < ? AND id = ?", [time() - (12 * 60 * 60), $this->user_id])) {
                    $database->query("UPDATE users SET lastview = ?, ip = ? WHERE id = ?", [time(), Utilities::getIpAddress(), $this->user_id]);
                }

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

                $this->has_authenticated_as_an_admin = $_SESSION["SB_ADMIN_AUTHED"] ?? null;
            }
        }
    }

    public function isUserLoggedIn(): bool
    {
        return $this->is_logged_in;
    }

    public function getUserID(): ?int
    {
        return $this->is_logged_in ? $this->user_id : null;
    }

    public function getUserData(): ?array
    {
        return $this->is_logged_in
            ? $this->user_data
            : [
                'comfortable_rating' => 'general',
                'blacklisted_tags' => $this->default_tags_blacklist,
            ];
    }

    public function getUserStatData(): array
    {
        return $this->is_logged_in ? $this->user_stat_data : [];
    }

    public function getUserBanData(): ?array
    {
        return $this->user_ban_data ?: null;
    }

    public function isUserAdmin(): bool
    {
        return $this->is_logged_in && ($this->user_data['powerlevel'] ?? 0) >= 3;
    }

    public function hasUserAuthenticatedAsAnAdmin(): bool
    {
        return $this->isUserAdmin() && $this->has_authenticated_as_an_admin;
    }

    public function getUserBlacklistedTags(): array
    {
        return $this->is_logged_in
            ? $this->user_data['blacklisted_tags'] ?? $this->default_tags_blacklist
            : $this->default_tags_blacklist;
    }

    public function isUserOver18(): bool
    {
        if ($this->is_logged_in) {
            $age = date_diff(date_create($this->user_data['birthdate']), date_create('today'))->y;

            return $age >= 18;
        } else {
            return false;
        }
    }

    public function getDefaultBlacklistedTags()
    {
        return $this->default_tags_blacklist;
    }
}