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

namespace SquareBracket;

use Exception;
use Parsedown;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

use BluffingoCore\Database;
use BluffingoCore\Profiler;

use SquareBracket\UserRoleEnum;

class SquareBracketTwigExtension extends AbstractExtension
{
    private SquareBracket $orange;
    private Database $database;
    private Profiler $profiler;
    private Storage $storage;
    private Authentication $authentication;
    private $twig;

    public function __construct(SquareBracket $orange, $twig)
    {
        $this->orange = $orange;
        $this->database = $this->orange->getDatabaseClass();
        $this->profiler = $this->orange->getProfilerClass();
        $this->storage = $this->orange->getStorageClass();
        $this->authentication = $this->orange->getAuthenticationClass();
        $this->twig = $twig;
    }

    public function getFunctions(): array
    {
        $options = $this->orange->getLocalOptions();
        $forceOldUserlink = $options['useOldUserlinkImplementation'] ?? null;

        if (isset($forceOldUserlink)) {
            // user preference
            $userlink_function_name = $forceOldUserlink ? "userLinkLegacy" : "userLink";
        } elseif ($options["skin"] == "trinium") {
            // default to new implementation on trinium (this logic should be swapped later)
            $userlink_function_name = "userLink";
        } else { // Utilities::isLegacyFrontend()
            // otherwise use the old implementation.
            $userlink_function_name = "userLinkLegacy";
        }

        // TODO: clean this up HOLY SHIT -chaziz 4/7/2025
        return [
            new TwigFunction('submission_view', [$this, 'uploadView']),
            new TwigFunction('thumbnail', [$this, 'getUploadThumbnail']),
            new TwigFunction('user_link', [$this, $userlink_function_name], ['is_safe' => ['html']]),
            new TwigFunction('profile_picture', function ($username) {
                return $this->storage->getUserProfilePicture($username, false);
            }),
            new TwigFunction('profile_banner', function ($username) {
                return $this->storage->getUserProfileBanner($username);
            }),
            new TwigFunction('profiler_stats', function () {
                $this->profiler->getStats();
            }),
            new TwigFunction('db_profiler_info', function () {
                return $this->profiler->getDatabaseProfilingReport();
            }),
            new TwigFunction('version_banner', function () {
                echo (new VersionNumber)->outputVersionBanner();
            }),
            new TwigFunction('remove_notification', function () {
                unset($_SESSION["notif_message"]);
                unset($_SESSION["notif_color"]);
            }),
            new TwigFunction('show_ratings', [$this, 'displayUploadRatings']),
            new TwigFunction('notification_icon', function ($type) {
                return "biscuit-icon b-$type";
            }),
            new TwigFunction('pagination', [$this, 'pagination'], ['is_safe' => ['html']]),
            new TwigFunction('header_main_links', [$this, 'headerMainLinks']),
            new TwigFunction('header_user_links', [$this, 'headerUserLinks']),
            new TwigFunction('header_user_account_links', [$this, 'headerUserAccountLinks']),
            new TwigFunction('footer_links', [$this, 'footerLinks']),
            new TwigFunction('sidebar_following_users', [$this, 'sidebarFollowingUsers']),
            new TwigFunction('get_css_file_date', [$this, 'getCssFileDate']),
            new TwigFunction('submission_box', [$this, 'smallUploadBox'], ['is_safe' => ['html']]),
            new TwigFunction('comment', [$this, 'comment'], ['is_safe' => ['html']]),
            new TwigFunction('localize', [$this, 'localize']),
            new TwigFunction('truncate_number', [$this, 'truncateNumber']),
            new TwigFunction('convert_time', [$this, 'convertTime']),
            new TwigFunction('get_user_data_cache', [$this, 'getUserDataCache']),
            // BOOTSTRAP/FINALIUM FRONTEND COMPATIBILITY (DO NOT USE THIS ON TRINIUM)
            new TwigFunction('icon', [$this, 'legacyIcon'], ['is_safe' => ['html']]),
            // ---------------------------
        ];
    }

    public function getFilters()
    {
        return [
            new TwigFilter('relative_time', [$this, 'relativeTime']),

            new TwigFilter('calculate_age', [Utilities::class, 'calculateAge']),
            new TwigFilter('calculate_age_from', [Utilities::class, 'calculateAgeFrom']),

            new TwigFilter('localize_date', function ($date, $dateFormat = 'medium', $timeFormat = 'medium', $pattern = null) {
                $localization = $this->orange->getLocalizationClass();
                return $localization->formatDate($date, $dateFormat, $timeFormat, $pattern);
            }, ['is_safe' => ['html']]),

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
            new TwigFilter('markdown_user_written', function ($text, $enableHeaders = false) {
                if ($enableHeaders) {
                    $markdown = new Parsedown();
                } else {
                    $markdown = new ParsedownExtension();
                }
                $markdown->setSafeMode(true);
                $markdown->setUrlsLinked(true);
                $markdown->setBreaksEnabled(true);

                $parsed_text = $markdown->text($text);

                $parsed_text = $this->parseHashtags($parsed_text);
                $parsed_text = $this->parseUserMentions($parsed_text);
                $parsed_text = $this->parseCustomEmojis($parsed_text);

                return $parsed_text;
            }, ['is_safe' => ['html']]),

            // Markdown function for any journals.
            new TwigFilter('markdown_user_journal', function ($text) {
                $markdown = new Parsedown();
                $markdown->setSafeMode(true);
                $markdown->setUrlsLinked(true);

                $parsed_text = $markdown->text($text);

                $parsed_text = $this->parseHashtags($parsed_text);
                $parsed_text = $this->parseUserMentions($parsed_text);
                $parsed_text = $this->parseCustomEmojis($parsed_text);

                return $parsed_text;
            }, ['is_safe' => ['html']]),

            // Markdown function for info pages. **NOT SANITIZED, DON'T LET IT EVER TOUCH USER INPUT**
            new TwigFilter('markdown_info_page', function ($text) {
                $branding = $this->orange->getBrandingSettings();
                $markdown = new Parsedown();

                // replace hardcoded dummy strings with proper strings
                $text = str_replace("OpenSBInstanceName", $branding["name"], $text);

                return $markdown->text($text);
            }, ['is_safe' => ['html']]),

            // Markdown function for non-inline text. **NOT SANITIZED, DON'T LET IT EVER TOUCH USER INPUT**
            new TwigFilter('markdown_unsafe', function ($text) {
                $markdown = new Parsedown();
                return $markdown->text($text);
            }, ['is_safe' => ['html']]),
        ];
    }

    private function parseHashtags($string): array|string|null
    {
        return preg_replace('/(?<!=|\b|&)#([a-z0-9_]+)/i', '<a href="/search?tags=$1">#$1</a>', $string);
    }

    private function parseUserMentions($string): array|string|null
    {
        return preg_replace('/(?<!=|\b|&)@([a-z0-9_]+(?:@[a-z0-9.-]+)?)/i', '<a href="/user/$1">@$1</a>', $string);
    }

    private function parseCustomEmojis($string)
    {
        return preg_replace_callback('/:([a-z0-9_]+):/i', function ($matches) {
            $emoji_name = strtolower($matches[1]);
            // check if emoji exists so we dont load nothing
            if (file_exists('../dynamic/emojis/' . $emoji_name . '.png')) {
                return '<img class="emoji" src="/dynamic/emojis/' . $emoji_name . '.png" alt=":' . $emoji_name . ':" />';
            } else {
                return ':' . $emoji_name . ':';
            }
        }, $string);
    }

    public function convertTime($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        // format the time
        if ($hours > 0) {
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        } else {
            return sprintf("%d:%02d", $minutes, $seconds);
        }
    }

    function truncateNumber($number)
    {
        if ($number < 1000) {
            return (string)$number;
        }

        $suffixes = ['', 'k', 'm', 'b', 't'];
        $suffixIndex = 0;

        while ($number >= 1000 && $suffixIndex < count($suffixes) - 1) {
            $number /= 1000;
            $suffixIndex++;
        }

        return number_format($number, ($number >= 100 && $number < 1000) ? 0 : 2) . $suffixes[$suffixIndex];
    }

    /**
     * Relative time function.
     */
    function relativeTime($time)
    {
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

    /**
     * @throws Exception
     */
    public function uploadView($submission_data)
    {
        if (!$submission_data) {
            throw new Exception('uploadView is missing data!');
        }

        if ($submission_data["type"] == 0) {
            echo $this->twig->render("player.twig", ['submission' => $submission_data]);
        }

        if ($submission_data["type"] == 2) {
            echo $this->twig->render("image.twig", ['submission' => $submission_data]);
        }

        // fyi: opensb still doesn't fully support music uploads.
        if ($submission_data["type"] == 3) {
            echo $this->twig->render("music.twig", ['submission' => $submission_data]);
        }
    }

    public function getUploadThumbnail($id, $type, $custom)
    {
        $data = null;

        if ($type == 0) {
            $data = $this->storage->getVideoUploadThumbnail($id, $custom);
        }
        if ($type == 2) {
            $data = $this->storage->getImageUploadThumbnail($id, $custom);
        }

        return $data;
    }

    // new userlink used on trinium
    public function userLink($user): string
    {
        // get user info
        $username = htmlspecialchars($user["info"]["username"]);
        $displayName = htmlspecialchars($user["info"]["displayname"]);
        $color = $user["info"]["color"];
        $powerlevel =  $user["info"]["powerlevel"];

        // Define common values
        $href = "/user/" . $username;
        $class = "userlink-" . $username;
        $style = "color:" . $color;

        // if user is staff
        if ($powerlevel > 1) {
            $staff_icon = '<div class="biscuit-icon staff"></div>';
        } else {
            $staff_icon = '';
        }

        if (mb_strtolower($username) === mb_strtolower($displayName)) {
            // if username matches display name
            $displayText = sprintf(
                '
<div class="userlink-bullshit">
<span style="%s">@%s</span>
%s
</div>',
                $style,
                $username,
                $staff_icon
            );
        } else {
            // if theyre different
            $displayText = sprintf(
                '
<div class="userlink-displayname">%s</div>
<div class="userlink-bullshit">
<a class="userlink-handle" style="text-decoration: none;" href="%s">@%s</a> %s
</div>
',
                $displayName,
                $href,
                $username,
                $staff_icon
            );
        }

        // return link
        return sprintf(
            '<div class="userlink"><a class="%s" style="%s" href="%s">%s</a></div>',
            $class,
            $style,
            $href,
            $displayText
        );
    }

    // old userlink used on bootstrap and finalium
    public function userLinkLegacy($user): string
    {
        $username = htmlspecialchars($user['info']['username']);
        $color = $user["info"]["color"];
        // the old userlink function used to show if someone was staff, this was implemented around april 2023.
        $powerlevel = $user["info"]["powerlevel"];

        $userlink = sprintf(
            '<a class="userlink userlink-%s" %shref="/user/%s">%s</a>',
            $username,
            $this->orange->isHitchhiker() ? '' : "style=\"color:{$color};\" ",
            $username,
            $username
        );

        if ($powerlevel > 1) {
            $staff_icon = $this->legacyIcon("shield", 14);

            return sprintf(
                '%s %s',
                $userlink,
                $staff_icon
            );
        } else {
            return $userlink;
        }
    }

    public function displayUploadRatings(array $ratings): void
    {
        $icons = [
            'full' => "biscuit-icon star-full",
            'half' => "biscuit-icon star-half",
            'empty' => "biscuit-icon star-empty"
        ];

        if (!isset($ratings['average']) || !is_numeric($ratings['average'])) {
            echo str_repeat("<i class='{$icons['empty']}'></i>", 5);
            return;
        }

        $average = (string)$ratings['average'];
        $fullStars = (int)$average[0];
        $halfStar = isset($average[2]) && $average[2] !== '0';
        $totalStars = 0;

        for ($i = 0; $i < $fullStars; $i++) {
            echo "<i class='{$icons['full']}'></i>";
            $totalStars++;
        }

        if ($halfStar) {
            echo "<i class='{$icons['half']}'></i>";
            $totalStars++;
        }

        while ($totalStars < 5) {
            echo "<i class='{$icons['empty']}'></i>";
            $totalStars++;
        }
    }

    public function pagination($levels, $lpp, $url, $current)
    {
        return $this->twig->render('components/pagination.twig', ['levels' => $levels, 'lpp' => $lpp, 'url' => $url, 'current' => $current]);
    }

    public function headerMainLinks()
    {
        $array = [
            "home" => [
                "name" => $this->localize("home"), // Home
                "url" => "/",
            ],
            "browse" => [
                "name" => $this->localize("browse"), // Browse
                "url" => "/browse",
            ],
            "members" => [
                "name" => $this->localize("members"), // Members
                "url" => "/users",
            ],
        ];

        return $array;
    }

    public function headerUserLinks()
    {
        $options = $this->orange->getLocalOptions();

        if ($this->authentication->isUserLoggedIn()) {
            $username = $this->authentication->getUserData()["name"];

            $array = [
                "profile" => [
                    "name" => $this->localize("my_profile"), // My profile
                    "url" => "/user/" . $username,
                ],
                "my_uploads" => [
                    "name" => $this->localize("my_uploads"), // My uploads
                    "url" => "/my_uploads",
                ],
                "settings" => [
                    "name" => $this->localize("account_settings"), // Account settings
                    "url" => "/settings",
                ],
                "upload" => [
                    "name" => $this->localize("new_upload"), // Upload (New upload)
                    "url" => "/upload",
                ],
                "write" => [
                    "name" => $this->localize("new_journal"), // Write (New journal)
                    "url" => "/write",
                ],
                "logout" => [
                    "name" => $this->localize("logout"), // Logout
                    "url" => "/logout",
                ],
            ];

            // remove upload link on finalium 1, bootstrap and trinium
            if ($options["skin"] != "bootstrap" && $options["theme"] != "classic") {
                if (Utilities::isLegacyFrontend() || $options["skin"] == "trinium") {
                    unset($array["upload"]);
                }
            }

            // remove write link on trinium
            if ($options["skin"] == "trinium") {
                unset($array["write"]);
            }

            if ($this->authentication->userHasRole(UserRoleEnum::Moderator)) {
                $arrayThatContainsOnlyTheLinkToTheDashboard = [
                    "dashboard" => [
                        "name" => $this->localize("dashboard"), // Dashboard
                        "url" => "/dashboard",
                    ],
                ];
                // Merge admin item with the rest of the array
                $array = array_merge($arrayThatContainsOnlyTheLinkToTheDashboard, $array);
            }
        } else {
            $array = [
                "login" => [
                    "name" => $this->localize("login"), // Login
                    "url" => "/login",
                ],
                "register" => [
                    "name" => $this->localize("register"), // Register
                    "url" => "/register",
                ],
            ];
        }

        return $array;
    }

    public function headerUserAccountLinks()
    {
        $accountsArray = $this->orange->getAccountsArray();

        $array = [];

        foreach ($accountsArray as $account) {
            $data = $this->database->result("SELECT name FROM users WHERE id = ?", [$account["userid"]]);

            $array[] = [
                "id" => $account["userid"],
                "username" => $data,
            ];
        }

        return $array;
    }

    public function sidebarFollowingUsers()
    {
        $userid = $this->authentication->getUserID();

        $users = $this->database->fetchArray(
            $this->database->query("SELECT s.* FROM user_follows s JOIN users u ON s.user = u.id WHERE s.user = ?", [$userid])
        );

        $array = [];

        foreach ($users as $user) {
            $data = $this->database->result("SELECT name FROM users WHERE id = ?", [$user["id"]]);

            $array[] = [
                "id" => $user["user"],
                "username" => $data,
            ];
        }

        return $array;
    }

    public function footerLinks()
    {
        $array = [
            "theme" => [
                "name" => $this->localize("change_theme"),
                "url" => "/theme",
            ],
            "help" => [
                "name" => $this->localize("help"),
                "url" => "/help",
            ],
            "guidelines" => [
                "name" => $this->localize("community_guidelines"),
                "url" => "/guidelines",
            ],
            "tos" => [
                "name" => $this->localize("terms_of_service"),
                "url" => "/tos",
            ],
            "privacy" => [
                "name" => $this->localize("privacy_policy"),
                "url" => "/privacy",
            ],
            "staff" => [
                "name" => $this->localize("staff"),
                "url" => "/staff",
            ],
        ];

        if ($this->orange->getLocalOptions()["skin"] == "bootstrap") {
            // Oops. Ugly!
            $version_array = [
                "version" => [
                    "name" => $this->localize("version"),
                    "url" => "/version",
                ],
            ];

            $array = array_merge($version_array, $array);
        }

        if ($this->orange->isChazizSquareBracketInstance()) {
            if (!$this->orange->isFulpTube()) {
                $array["brickface"] = [
                    "name" => $this->localize("kylarz_link"),
                    "url" => "https://brickface.squarebracket.pw/",
                ];
            }

            $array["discord"] = [
                "name" => "Discord",
                "url" => "https://discord.gg/tzkSpxpmSD",
            ];
        }

        return $array;
    }

    public function getCssFileDate()
    {
        // TODO: this should probably be changed to check the file date of the current theme, not just that of the
        // default theme on trinium -chaziz 1/13/2025.
        return filemtime(BLUFF_PUBLIC_PATH . "/assets/css/trinium-default.css");
    }

    // legacy functions used by finalium and bootstrap frontend only.

    public function legacyIcon($icon, $size)
    {
        if (!Utilities::isLegacyFrontend()) {
            trigger_error("legacyIcon function called outside of a legacy frontend.", E_USER_WARNING);
        }

        // this should be in common
        return $this->twig->render('bootstrap_icon.twig', ['icon' => $icon, 'size' => $size]);
    }

    // apparantly this is used on finalium for Some reason.
    public function smallUploadBox($submission)
    {
        return $this->twig->render('components/smallvideobox.twig', ['data' => $submission]);
    }

    public function comment($comment)
    {
        return $this->twig->render('components/comment.twig', ['data' => $comment]);
    }
    //

    public function localize($key, ...$args)
    {
        return $this->orange->getLocalizationClass()->translate($key, ...$args);
    }

    public function getUserDataCache(): array
    {
        return UserData::getUserDataCache();
    }
}
