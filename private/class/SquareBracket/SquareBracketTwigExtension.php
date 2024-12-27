<?php

namespace SquareBracket;

use Parsedown;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SquareBracketTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        global $profiler, $orange;

        if ($orange->getLocalOptions()["skin"] == "biscuit" || $orange->getLocalOptions()["skin"] == "charla") {
            $userlink_function_name = "UserLink";
        } else {
            $userlink_function_name = "UserLinkOld";
        }

        return [
            new TwigFunction('submission_view', [$this, 'submissionView']),
            new TwigFunction('thumbnail', [$this, 'thumbnail']),
            new TwigFunction('user_link', [$this, $userlink_function_name], ['is_safe' => ['html']]),
            new TwigFunction('profile_picture', [$this, 'profilePicture']),
            new TwigFunction('profile_picture_admin', [$this, 'profilePictureAdmin']),
            new TwigFunction('profile_banner', [$this, 'profileBanner']),
            new TwigFunction('profiler_stats', function () use ($profiler) {
                $profiler->getStats();
            }),
            new TwigFunction('version_banner', function () use ($orange) {
                echo (new VersionNumber)->printVersionForOutput();
            }),
            new TwigFunction('remove_notification', [$this, 'removeNotification']),
            new TwigFunction('show_ratings', [$this, 'showRatings']),
            new TwigFunction('notification_icon', [$this, 'notificationIcon']),
            new TwigFunction('pagination', [$this, 'pagination'], ['is_safe' => ['html']]),
            new TwigFunction('header_main_links', [$this, 'headerMainLinks']),
            new TwigFunction('header_user_links', [$this, 'headerUserLinks']),
            new TwigFunction('header_user_account_links', [$this, 'headerUserAccountLinks']),
            new TwigFunction('sidebar_following_users', [$this, 'sidebarFollowingUsers']),
            new TwigFunction('get_css_file_date', [$this, 'getCSSFileDate']),
            new TwigFunction('submission_box', [$this, 'submissionBox'], ['is_safe' => ['html']]),
            new TwigFunction('comment', [$this, 'comment'], ['is_safe' => ['html']]),
            new TwigFunction('localize', [$this, 'localize']),
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('relative_time', [$this, 'relativeTime']),

            new TwigFilter('calculate_age', [Utilities::class, 'calculateAge']),
            new TwigFilter('calculate_age_from', [Utilities::class, 'calculateAgeFrom']),

            // Markdown function for non-inline text, sanitized.
            new TwigFilter('markdown', function ($text) {
                $markdown = new Parsedown();
                $markdown->setSafeMode(true);
                return $markdown->text($text);
            }, ['is_safe' => ['html']]),

            // Markdown function for inline text, sanitized.
            new TwigFilter('markdown_inline', function ($text) {
                $markdown = new Parsedown();
                $markdown->setSafeMode(true);
                return $markdown->line($text);
            }, ['is_safe' => ['html']]),

            // Markdown function for any posts.
            new TwigFilter('markdown_user_written', function ($text) {
                $markdown = new ParsedownExtension();
                $markdown->setSafeMode(true);
                $markdown->setUrlsLinked(true);

                $parsed_text = $markdown->text($text);

                // Hashtags
                $parsed_text = preg_replace('/(?<!=|\b|&)#([a-z0-9_]+)/i', '<a href="/search?tags=$1">#$1</a>', $parsed_text);

                // Mentions
                $parsed_text = preg_replace('/(?<!=|\b|&)@([a-z0-9_]+(?:@[a-z0-9.-]+)?)/i', '<a href="/user/$1">@$1</a>', $parsed_text);

                // Emojis
                $parsed_text = preg_replace_callback('/:([a-z0-9_]+):/i', function($matches) {
                    global $storage;
                    $emoji_name = strtolower($matches[1]);
                    // check if emoji exists so we dont load nothing
                    if ($storage->fileExists('../dynamic/emojis/' . $emoji_name . '.png')) {
                        return '<img class="emoji" src="/dynamic/emojis/' . $emoji_name . '.png" alt=":' . $emoji_name . ':" />';
                    } else {
                        return ':' . $emoji_name . ':';
                    }
                }, $parsed_text);

                return $parsed_text;

            }, ['is_safe' => ['html']]),

            // Markdown function for any journals.
            new TwigFilter('markdown_user_journal', function ($text) {
                $markdown = new Parsedown();
                $markdown->setSafeMode(true);
                $markdown->setUrlsLinked(true);

                $parsed_text = $markdown->text($text);

                // Hashtags
                $parsed_text = preg_replace('/(?<!=|\b|&)#([a-z0-9_]+)/i', '<a href="/search?tags=$1">#$1</a>', $parsed_text);

                // Mentions
                $parsed_text = preg_replace('/(?<!=|\b|&)@([a-z0-9_]+(?:@[a-z0-9.-]+)?)/i', '<a href="/user/$1">@$1</a>', $parsed_text);

                // Emojis
                $parsed_text = preg_replace_callback('/:([a-z0-9_]+):/i', function($matches) {
                    global $storage;
                    $emoji_name = strtolower($matches[1]);
                    // check if emoji exists so we dont load nothing
                    if ($storage->fileExists('../dynamic/emojis/' . $emoji_name . '.png')) {
                        return '<img class="emoji" src="/dynamic/emojis/' . $emoji_name . '.png" alt=":' . $emoji_name . ':" />';
                    } else {
                        return ':' . $emoji_name . ':';
                    }
                }, $parsed_text);

                return $parsed_text;

            }, ['is_safe' => ['html']]),

            // Markdown function for non-inline text. **NOT SANITIZED, DON'T LET IT EVER TOUCH USER INPUT**
            new TwigFilter('markdown_unsafe', function ($text) {
                $markdown = new Parsedown();
                return $markdown->text($text);
            }, ['is_safe' => ['html']]),
        ];
    }

    /**
     * Relative time function.
     */
    function relativeTime($time) {
        if ($time == 0) {
            return "unknown";
        }

        $time_difference = time() - $time;
        $units = [
            31536000 => 'year',
            2592000  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
            1        => 'second'
        ];

        foreach ($units as $unit => $text) {
            if ($time_difference < $unit) continue;
            $numberOfUnits = floor($time_difference / $unit);
            return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '') . ' ago';
        }

        return 'just now';
    }

    public function submissionView($submission_data)
    {
        global $twig;
        if (!$submission_data) {
            throw new CoreException('SubmissionView is null', 500);
        }
        if ($submission_data["type"] == 0) {
            echo $twig->render("player.twig", ['submission' => $submission_data]);
        }

        if ($submission_data["type"] == 2) {
            echo $twig->render("image.twig", ['submission' => $submission_data]);
        }

        // fyi: opensb still doesn't fully support music submissions.
        if ($submission_data["type"] == 3) {
            echo $twig->render("music.twig", ['submission' => $submission_data]);
        }
    }

    public function thumbnail($id, $type, $custom)
    {
        global $storage;

        $custom_location = '/dynamic/custom_thumbnails/' . $id . '.jpg';

        $data = null;

        if ($custom) {
            if ($storage->fileExists('..' . $custom_location)) {
                $data = $custom_location;
            }
        } else {
            if ($type == 0) {
                $data = $storage->getVideoThumbnail($id);
            }
            if ($type == 2) {
                $data = $storage->getImageThumbnail($id);
            }
        }

        return $data;
    }

    public function profilePicture($username)
    {
        global $database, $storage;

        $id = Utilities::usernameToID($database, $username);
        $location = '/dynamic/pfp/' . $id . '.png';
        // don't bother with userdata since that might slow shit down
        $is_banned = $database->fetch("SELECT * FROM user_bans WHERE userid = ?", [$id]);

        if ($is_banned) {
            $data = "/assets/profiledef.svg";
        } else {
            if ($storage->fileExists('..' . $location)) {
                $data = $location;
            } else {
                $data = "/assets/profiledef.svg";
            }
        }

        return $data;
    }

    //
    public function profilePictureAdmin($username)
    {
        global $database, $storage;

        $id = Utilities::usernameToID($database, $username);
        $location = '/dynamic/pfp/' . $id . '.png';
        if ($storage->fileExists('..' . $location)) {
            $data = $location;
        } else {
            $data = "/assets/profiledef.svg";
        }

        return $data;
    }

    public function profileBanner($username)
    {
        global $database, $storage;

        $id = Utilities::usernameToID($database, $username);
        $location = '/dynamic/banners/' . $id . '.png';

        if ($storage->fileExists('..' . $location)) {
            $data = $location;
        } else {
            $data = "/assets/biscuit_banner.svg";
        }
        return $data;
    }

    // new userlink used on biscuit
    public function UserLink($user): string
    {
        // Extract and sanitize user information
        $username = htmlspecialchars($user["info"]["username"]);
        $displayName = htmlspecialchars($user["info"]["displayname"]);
        $customColor = htmlspecialchars($user["info"]["customcolor"]);

        // Define common values
        $href = "/user/" . $username;
        $class = "userlink userlink-" . $username;
        $style = "color:" . $customColor;

        // Determine the display text
        if ($username === $displayName) {
            $displayText = sprintf('<span style="%s">@%s</span>', $style, $username);
        } else {
            $displayText = sprintf(
                '%s <a class="userlink-handle" style="text-decoration: none;" href="%s">@%s</a>',
                $displayName,
                $href,
                $username
            );
        }

        // Return the formatted link
        return sprintf('<a class="%s" style="%s" href="%s">%s</a>', $class, $style, $href, $displayText);
    }

    // old userlink used on bootstrap and finalium
    public function UserLinkOld($user): string
    {
        return <<<HTML
<a class="userlink userlink-{$user["info"]["username"]}" style="color:{$user["info"]["customcolor"]};" href="/user/{$user["info"]["username"]}">{$user["info"]["username"]}</a>
HTML;
    }

    public function removeNotification()
    {
        unset($_SESSION["notif_message"]);
        unset($_SESSION["notif_color"]);
    }

    public function showRatings($ratings): void
    {
        $full = "biscuit-icon star-full";
        $half = "biscuit-icon star-half";
        $empty = "biscuit-icon star-empty";

        $full_stars = substr($ratings["average"], 0, 1);
        $half_stars = substr($ratings["average"], 2, 1);

        $number = 0;

        for ($x = 0; $x < $full_stars; $x++) {
            $number++;
            echo "<i class='$full'></i>";
        }

        if ($half_stars) {
            $number++;
            if ($full_stars != 4) {
                echo "<i class='$half'></i>";
            } else {
                echo "<i class='$full'></i>";
            }
        }

        while ($number != 5) {
            $number++;
            echo "<i class='$empty'></i>";
        }

    }

    public function notificationIcon($type)
    {
        $icon = "biscuit-icon b-$type";

        return $icon;
    }

    public function pagination($levels, $lpp, $url, $current)
    {
        global $twig;
        return $twig->render('components/pagination.twig', ['levels' => $levels, 'lpp' => $lpp, 'url' => $url, 'current' => $current]);
    }

    public function headerMainLinks()
    {
        $array = [
            "browse" => [
                "name" => "Browse",
                "url" => "/browse",
            ],
            "members" => [
                "name" => "Members",
                "url" => "/users",
            ],
        ];

        return $array;
    }

    public function headerUserLinks()
    {
        global $auth, $orange, $isDebug;

        if ($auth->isUserLoggedIn()) {
            $username = $auth->getUserData()["name"];

            $array = [
                "profile" => [
                    "name" => "My profile",
                    "url" => "/user/" . $username,
                ],
                "my_uploads" => [
                    "name" => "My uploads",
                    "url" => "/my_uploads",
                ],
                "settings" => [
                    "name" => "Account settings",
                    "url" => "/settings",
                ],
                "upload" => [
                    "name" => "Upload",
                    "url" => "/upload",
                ],
                "write" => [
                    "name" => "Write",
                    "url" => "/write",
                ],
                "logout" => [
                    "name" => "Log out",
                    "url" => "/logout",
                ],
            ];

            if ($auth->isUserAdmin()) {
                $array["admin"] = [
                    "name" => "Admin",
                    "url" => "/admin",
                ];
            }

            // remove upload link on finalium 1, bootstrap and charla
            if ($orange->getLocalOptions()["skin"] == "finalium"
                || $orange->getLocalOptions()["skin"] == "bootstrap"
            || $orange->getLocalOptions()["skin"] == "charla") {
                unset($array["upload"]);
            }

            // remove write link on charla
            if ($orange->getLocalOptions()["skin"] == "charla") {
                unset($array["write"]);
            }
        } else {
            $array = [
                "login" => [
                    "name" => "Login",
                    "url" => "/login",
                ],
                "register" => [
                    "name" => "Register",
                    "url" => "/register",
                ],
            ];
        }

        return $array;
    }

    public function headerUserAccountLinks()
    {
        global $orange, $database;
        $accountsArray = $orange->getAccountsArray();

        $array = [];

        foreach ($accountsArray as $account) {
            $data = $database->result("SELECT name FROM users WHERE id = ?", [$account["userid"]]);

            $array[] = [
                "id" => $account["userid"],
                "username" => $data,
            ];
        }

        return $array;
    }

    public function sidebarFollowingUsers() {
        global $auth, $database;

        $userid = $auth->getUserID();

        //$allUsers = query("SELECT $userfields s.* FROM user_follows s JOIN users u ON s.user = u.id WHERE s.id = ?", [$userdata['id']]);
        $users = $database->fetchArray(
            $database->query("SELECT s.* FROM user_follows s JOIN users u ON s.user = u.id WHERE s.user = ?", [$userid])
        );

        $array = [];

        foreach ($users as $user) {
            $data = $database->result("SELECT name FROM users WHERE id = ?", [$user["id"]]);

            $array[] = [
                "id" => $user["user"],
                "username" => $data,
            ];
        }

        return $array;
    }

    public function getCSSFileDate()
    {
        return filemtime(SB_PUBLIC_PATH . "/assets/css/default.css");
    }

    public function submissionBox($submission)
    {
        global $twig;
        return $twig->render('components/smallvideobox.twig', ['data' => $submission]);
    }

    public function comment($comment)
    {
        global $twig;
        return $twig->render('components/comment.twig', ['data' => $comment]);
    }

    public function localize($key, ...$args) {
        global $localization;
        return $localization->getMessage($key, ...$args);
    }
}