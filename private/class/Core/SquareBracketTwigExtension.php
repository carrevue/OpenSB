<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

namespace Core;

use Exception;
use Parsedown;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

use Data\User\UserRoleEnum;
use Data\Upload\UploadTypeEnum;
use Data\User\UserData;
use Data\User\UserFlags;

/**
 * class SquareBracketTwigExtension
 */
class SquareBracketTwigExtension extends AbstractExtension
{
    /**
     * @var SquareBracket The core OpenSB class.
     */
    private SquareBracket $sb;

    /**
     * @var Database The Database class.
     */
    private Database $database;

    /**
     * @var Profiler The Profiler class.
     */
    private Profiler $profiler;

    /**
     * @var Storage The Storage class.
     */
    private Storage $storage;

    /**
     * @var Authentication The authentication class.
     */
    private Authentication $authentication;

    /**
     * @var Environment The Twig environment.
     */
    private Environment $twig;

    /**
     * @var array The current skin's options.
     */
    private array $skin_options;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     * @param Templating $templating
     * @param Environment $twig
     *
     * @return void
     */
    public function __construct(SquareBracket $sb, Templating $templating, Environment $twig)
    {
        $this->sb = $sb;
        $this->database = $this->sb->getDatabaseClass();
        $this->profiler = $this->sb->getProfilerClass();
        $this->storage = $this->sb->getStorageClass();
        $this->authentication = $this->sb->getAuthenticationClass();
        $this->twig = $twig;
        
        $this->skin_options = $this->sb->getSkinThemeOptions();
    }

    /**
     * function getFunctions
     *
     * @return array
     */
    public function getFunctions(): array
    {
        $userlink_function_name = ($this->skin_options["use_old_userlink"] ?? false) ? "userLinkLegacy" : "userLink";

        // TODO: clean this up HOLY SHIT -chaziz 4/7/2025
        return [
            new TwigFunction('upload_view', [$this, 'uploadView']),
            new TwigFunction('thumbnail', [$this, 'getUploadThumbnail']),
            new TwigFunction('user_link', [$this, $userlink_function_name], ['is_safe' => ['html']]),
            new TwigFunction('profile_picture', function ($user) {
                return $this->storage->getUserProfilePicture($user ?? 0, $this->authentication->userHasRole(UserRoleEnum::Moderator));
            }),
            new TwigFunction('profile_banner', function ($user) {
                return $this->storage->getUserProfileBanner($user ?? 0);
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
            new TwigFunction('show_ratings', [$this, 'displayUploadRatings'], ['is_safe' => ['html']]),
            new TwigFunction('notification_icon', [$this, 'getNotificationIcon'], ['is_safe' => ['html']]),
            new TwigFunction('pagination', [$this, 'pagination'], ['is_safe' => ['html']]),
            new TwigFunction('sidebar_main_links', [$this, 'sidebarMainLinks']),
            new TwigFunction('sidebar_library_links', [$this, 'sidebarLibraryLinks']),
            new TwigFunction('sidebar_user_links', [$this, 'sidebarUserLinks']),
            new TwigFunction('header_user_links', [$this, 'headerUserLinks']),
            new TwigFunction('header_user_switcher_links', [$this->authentication, 'getUsersFromAccount']),
            new TwigFunction('footer_links', [$this, 'footerLinks']),
            new TwigFunction('get_css_file_timestamp', [$this->sb, 'getCurrentSkinCssTimestamp']),
            new TwigFunction('upload_box', [$this, 'smallUploadBox'], ['is_safe' => ['html']]),
            new TwigFunction('comment', [$this, 'comment'], ['is_safe' => ['html']]),
            new TwigFunction('localize', [$this, 'localize']),
            //new TwigFunction('truncate_number', [$this, 'truncateNumber']),
            new TwigFunction('convert_time', [$this, 'convertTime']),
            new TwigFunction('get_user_data_cache', [$this, 'getUserDataCache']),
            new TwigFunction('icon', [$this, 'getIcon'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * function getFilters
     *
     * @return mixed
     */
    public function getFilters()
    {
        return [
            new TwigFilter('relative_time', function ($time) {
                $localization = $this->sb->getLocalizationClass();
                return $localization->formatRelativeTime($time);
            }, ['is_safe' => ['html']]),

            new TwigFilter('calculate_age', [Utilities::class, 'calculateAge']),
            new TwigFilter('calculate_age_from', [Utilities::class, 'calculateAgeFrom']),

            new TwigFilter('format_date', function ($date, $dateFormat = 'medium', $timeFormat = 'medium', $pattern = null) {
                $localization = $this->sb->getLocalizationClass();
                return $localization->formatDate($date, $dateFormat, $timeFormat, $pattern);
            }, ['is_safe' => ['html']]),

            new TwigFilter('format_number', function ($number) {
                $localization = $this->sb->getLocalizationClass();
                return $localization->formatNumber($number);
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

            // Markdown function for any comments.
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

                // on finalium, replace paragraphs with breaks
                if ($this->sb->getCurrentSkinName() == "finalium") {
                    $parsed_text = str_replace('</p>', '<br><br>', $parsed_text);
                    $parsed_text = str_replace('<p>', '', $parsed_text);
                    // remove the last breaks
                    $parsed_text = preg_replace('/<br><br>(?!.*<br><br>)/s', '', $parsed_text);
                }

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
                $branding = $this->sb->getBrandingSettings();
                $markdown = new Parsedown();

                // hide sections not meant to be seen outside of squarebracket / fulptube.rocks
                if (!$this->sb->isChazizInstance()) {
                    $text = preg_replace('/<!--\s*chazizsb\s+start\s*-->.*?<!--\s*chazizsb\s+end\s*-->/s', '', $text);
                }

                if (!$this->sb->isFulpTubeMode()) {
                    $text = preg_replace('/<!--\s*fulptube\s+start\s*-->.*?<!--\s*fulptube\s+end\s*-->/s', '', $text);
                }

                $text = $markdown->text($text);

                // replace hardcoded dummy strings with proper strings
                $text = str_replace("[OpenSBInstanceName]", $branding["name"], $text);
                $text = str_replace("[size_limit]", Utilities::formatBytes(ini_get('upload_max_filesize')), $text);

                return $text;
            }, ['is_safe' => ['html']]),

            // Markdown function for non-inline text. **NOT SANITIZED, DON'T LET IT EVER TOUCH USER INPUT**
            new TwigFilter('markdown_unsafe', function ($text) {
                $markdown = new Parsedown();
                return $markdown->text($text);
            }, ['is_safe' => ['html']]),
        ];
    }

    /**
     * function parseHashtags
     *
     * @param mixed $string
     *
     * @return array|string|null
     */
    private function parseHashtags($string): array|string|null
    {
        return preg_replace('/(?<!=|\b|&)#([a-z0-9_]+)/i', '<a href="/search?tags=$1">#$1</a>', $string);
    }

    /**
     * function parseUserMentions
     *
     * @param mixed $string
     *
     * @return array|string|null
     */
    private function parseUserMentions($string): array|string|null
    {
        return preg_replace('/(?<![=\/\w&])@([a-z0-9_]+(?:@[a-z0-9.-]+)?)/i', '<a href="/user/$1">@$1</a>', $string);
    }

    /**
     * function parseCustomEmojis
     *
     * @param mixed $string
     *
     * @return mixed|string
     */
    private function parseCustomEmojis($string)
    {
        return preg_replace_callback('/:([a-z0-9_]+):/i', function ($matches) {
            $emoji_name = strtolower($matches[1]);
            // check if emoji exists so we dont load nothing
            if (file_exists('../dynamic/emojis/' . $emoji_name . '.png')) {
                return '<img class="emoji use-tooltip" src="/dynamic/emojis/' . $emoji_name . '.png" alt=":' . $emoji_name . ':" title=":' . $emoji_name . ':" />';
            } else {
                return ':' . $emoji_name . ':';
            }
        }, $string);
    }

    /**
     * function convertTime
     *
     * @param mixed $seconds
     *
     * @return mixed
     */
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

    /*
    function truncateNumber($number)
    {
        $localization = $this->sb->getLocalizationClass();
        return $localization->truncateNumber($number);
    }
    */

    /**
     * function uploadView
     *
     * @param array $upload_data
     * 
     * @note This is fucking stupid.
     *
     * @return void
     */
    public function uploadView($upload_data)
    {
        if (!$upload_data) {
            throw new Exception('uploadView is missing data!');
        }

        if ($upload_data["type"] == UploadTypeEnum::Video->value) {
            echo $this->twig->render("player.twig", ['upload' => $upload_data]);
        }

        if ($upload_data["type"] == UploadTypeEnum::Image->value) {
            echo $this->twig->render("image.twig", ['upload' => $upload_data]);
        }

        // fyi: opensb still doesn't fully support music uploads.
        if ($upload_data["type"] == UploadTypeEnum::Music->value) {
            echo $this->twig->render("music.twig", ['upload' => $upload_data]);
        }
    }

    /**
     * function getUploadThumbnail
     *
     * @param mixed $id
     * @param mixed $type
     * @param mixed $custom
     *
     * @return mixed
     */
    public function getUploadThumbnail($id, $type, $custom)
    {
        return $this->storage->getUploadThumbnail($id, $type, $custom);
    }
    
    /**
     * function userLink
     * 
     * new userlink used on trinium
     *
     * @param mixed $user
     *
     * @return string
     */
    public function userLink($user): string
    {
        // get user info
        $username = htmlspecialchars($user["info"]["username"]);
        $displayName = htmlspecialchars($user["info"]["displayname"]);
        $color = $user["info"]["color"];
        $powerlevel =  $user["info"]["powerlevel"];

        // common values
        $href  = "/user/{$username}";
        $class = "userlink-{$username}";
        $style = "color:{$color}";

        // if user is staff
        if ($powerlevel > 1) {
            $icon = '<span class="use-tooltip userlink-icon" title="Staff">' . $this->getIcon("staff") . '</span>';
        } else {
            $icon = '';
        }

        if (mb_strtolower($username) === mb_strtolower($displayName)) {
            // if username matches display name
            $displayText = sprintf(
                '<div class="userlink"><span class="userlink-name">@%s</span>%s</div>',
                $username,
                $icon
            );
        } else {
            // if theyre different
            $displayText = sprintf(
                '<div class="userlink"><span class="userlink-name">%s</span><span class="userlink-handle">@%s</span>%s</div>',
                $displayName,
                $username,
                $icon
            );
        }

        // output link
        return sprintf(
            '<div class="userlink-container"><a class="%s" style="%s" href="%s">%s</a></div>',
            $class,
            $style,
            $href,
            $displayText
        );
    }

    /**
     * function userLinkLegacy
     * 
     * old userlink used on bootstrap and finalium
     *
     * @param mixed $user
     *
     * @return string
     */
    public function userLinkLegacy($user): string
    {
        $username = htmlspecialchars($user['info']['username']);
        $displayName = htmlspecialchars($user["info"]["displayname"]);
        $color = $user["info"]["color"];
        // the old userlink function used to show if someone was staff, this was implemented around april 2023.
        $powerlevel = $user["info"]["powerlevel"];

        $userlink = sprintf(
            '<a class="userlink userlink-%s %s" %shref="/user/%s">%s</a>',
            $username,
            $this->sb->getCurrentSkinName() == "finalium" ? 'spf-link' : '',
            ($this->skin_options["userlink_disable_colors"] ?? false) ? '' : "style=\"color:{$color};\" ",
            $username,
            $displayName
        );

        if ($powerlevel > 1) {
            if ($this->sb->getCurrentSkinName() == "finalium") {
                // this STINKS. look into this later -chaziz 02/22/2026
                $staff_icon = $this->getIcon($this->sb->getTemplatingClass()->getFinaliumIconMap()["userlink_staff"], [12, 9]);
            } else {
                $staff_icon = $this->getIcon("shield", 14);
            }

            $staff_string = $this->localize("staff");

            return sprintf(
                '%s <span class="uix-tooltip userlink-icon" title="%s">%s</span>',
                $userlink,
                $staff_string,
                $staff_icon
            );
        } else {
            return $userlink;
        }
    }

    /**
     * function displayUploadRatings
     *
     * @param array $ratings
     * 
     * @note trinium specific
     *
     * @return string
     */
    public function displayUploadRatings(array $ratings)
    {
        if (!isset($ratings['average']) || empty($ratings['average'])) {
            return '<div class="star-rating-container" style="background: var(--secondary);"></div>';
        }

        $average = (float) $ratings['average'];
        $percentage = round($average * 20, 4);
        $percentage = max(0, min(100, $percentage));

        return sprintf(
            '<div class="star-rating-container" style="background: linear-gradient(to right, var(--warning) %1$s%%, var(--secondary) %1$s%%);"></div>',
            $percentage
        );
    }

    /**
     * function pagination
     *
     * @param mixed $levels
     * @param mixed $lpp
     * @param mixed $url
     * @param mixed $current
     *
     * @return mixed
     */
    public function pagination($levels, $lpp, $url, $current)
    {
        return $this->twig->render('components/pagination.twig', ['levels' => $levels, 'lpp' => $lpp, 'url' => $url, 'current' => $current]);
    }

    /**
     * function sidebarMainLinks
     *
     * @return array
     */
    public function sidebarMainLinks()
    {
        $options = $this->sb->getLocalOptions();

        // use different links for finalium
        if ($options["skin"] === "finalium") {
            $user_data = $this->authentication->getUserData();

            $menu = [
                "top" => [
                    "home" => [
                        "name" => $this->localize("home"),
                        "url"  => "/",
                        "icon" => "guide_home"
                    ],
                    "browse" => [
                        "name" => $this->localize("browse"),
                        "url"  => "/browse",
                        "icon" => "guide_uploads"
                    ],
                ],
                "bottom" => [
                    "members" => [
                        "name" => $this->localize("browse_members"),
                        "url"  => "/members",
                        "icon" => "guide_browse_members"
                    ],
                ],
            ];

            if ($this->authentication->isLoggedIn()) {
                $menu["top"]["profile"] = [
                    "name" => $this->localize("my_profile"),
                    "url"  => "/user/" . $user_data["name"],
                    "page" => "user " . $user_data["name"],
                    "icon" => "guide_my_profile"
                ];
            }

            return $menu;
        }

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
                "url" => "/members",
            ],
        ];

        if (isset($options["trinium_old_header"]) && $options["trinium_old_header"] == true) {
            unset($array["home"]);
        }

        return $array;
    }

    /**
     * function sidebarLibraryLinks
     *
     * @return array
     */
    public function sidebarLibraryLinks()
    {
        if ($this->sb->getCurrentSkinName() === "finalium") {
            $playlist_icon = "guide_collection"; // tied to finalium icon map
        } else {
            $playlist_icon = "collection";
        }

        $array = [
            "playlist1" => [
                "name" => $this->localize("playlist"),
                "url" => Utilities::isClassicSkin() ? "/playlist?list=test" : "/playlist/test",
                "icon" => $playlist_icon,
            ],
        ];

        return $array;
    }

    /**
     * function sidebarUserLinks
     *
     * @return array
     */
    public function sidebarUserLinks(): array
    {
        if ($this->authentication->isLoggedIn()) {
            // logged in: following
            $users = $this->database->fetchArray(
                $this->database->query(
                    "SELECT followed.id, followed.name, followed.title
                    FROM user_follows uf
                    JOIN users follower ON uf.user = follower.id
                    JOIN users followed ON uf.id = followed.id
                    WHERE uf.user = ?
                    AND followed.id NOT IN (SELECT user FROM user_bans)
                    ORDER BY followed.title",
                    [$this->authentication->getUserID()]
                )
            );
        } else {
            // logged out: featured
            $users = $this->database->fetchArray(
                $this->database->query(
                    "SELECT u.id, u.name, u.title
                    FROM users u
                    WHERE u.flags & ? = ?
                    ORDER BY u.title",
                    [UserFlags::FLAG_FEATURED->value, UserFlags::FLAG_FEATURED->value]
                )
            );
        }

        return array_map(
            fn($user) => ['id' => $user['id'], 'username' => $user['name'], 'displayname' => $user['title']],
            $users
        );
    }

    /**
     * function headerUserLinks
     *
     * @return array
     */
    public function headerUserLinks()
    {
        $options = $this->sb->getLocalOptions();

        $old_trinium_header = isset($options["trinium_old_header"]) && $options["trinium_old_header"] == true;

        if ($this->authentication->isLoggedIn()) {
            $username = $this->authentication->getUserData()["name"];

            $array = [
                "my_account" => [
                    "name" => $this->localize("my_account"), // My account
                    "url" => "/my_account",
                ],
                "my_profile" => [
                    "name" => $this->localize("my_profile"), // My profile
                    "url" => "/user/" . $username,
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

            // remove upload link on finalium, bootstrap and trinium
            if ($options["skin"] != "bootstrap" && $options["theme"] != "classic") {
                if (Utilities::isClassicSkin() || ($options["skin"] == "trinium" && !$old_trinium_header)) {
                    unset($array["upload"]);
                }
            }

            // remove write and logout link on trinium
            if ($options["skin"] == "trinium" && !$old_trinium_header) {
                unset($array["write"]);
                unset($array["logout"]);
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

    /**
     * function headerUserSwitcherLinks
     *
     * @return array
     */
    public function headerUserSwitcherLinks()
    {
        return $this->authentication->getUsersFromAccount();
    }

    /**
     * function footerLinks
     *
     * @return array
     */
    public function footerLinks()
    {
        $array = [
            "theme" => [
                "name" => $this->localize("change_theme"),
                "url" => "/theme",
            ],
            "about" => [
                "name" => $this->localize("about"),
                "url" => "/about",
            ],
            "help" => [
                "name" => $this->localize("help"),
                "url" => "/help",
            ],
            "news" => [
                "name" => $this->localize("site_news"),
                "url" => "/news",
            ],
            "tos" => [
                "name" => $this->localize("terms_of_service"),
                "url" => "/tos",
            ],
            "privacy" => [
                "name" => $this->localize("privacy_policy"),
                "url" => "/privacy",
            ],
            "guidelines" => [
                "name" => $this->localize("community_guidelines"),
                "url" => "/guidelines",
            ],
        ];

        if ($this->sb->isIncompleteFeaturesEnabled()) {
            $array["experiment_flags"] = [
                "name" => $this->localize("experiment_flags"),
                "url"  => "/experiment_flags",
            ];
        }

        if ($this->sb->isChazizInstance()) {
            if (!$this->sb->isFulpTubeMode()) {
                $array["brickface"] = [
                    "name" => $this->localize("kylarz_link"),
                    "url"  => "https://brickface.squarebracket.pw/",
                ];
            }

            $array["discord"] = [
                "name" => "Discord",
                "url"  => "https://discord.gg/jG3DaRf6Rm",
            ];
        }

        return $array;
    }

    /**
     * function getIcon
     *
     * @param mixed $icon
     * @param mixed $size
     * @param mixed $class
     *
     * @return mixed
     */
    public function getIcon($icon, $size = "16", $class = null)
    {
        if ($this->sb->getCurrentSkinName() === "finalium" && ($this->sb->getSkinThemeOptions()["finalium_hitchhiker_icons"] ?? false)) {
            $root_class = "icon";
            $svg = "skin/finalium/icons.svg";
        } elseif ($this->sb->getCurrentSkinName() === "bootstrap" || $this->sb->getCurrentSkinName() === "finalium") {
            $root_class = "bi";
            $svg = "bootstrap-icons.svg";
        } else {
            $root_class = "icon";
            $svg = "icons.svg";
        }

        if (is_array($size)) {
            $width = $size[0];
            $height = $size[1];
        } else {
            $width = $size;
            $height = $size;
        }

        return $this->twig->render(
            'icon.twig',
            [
                'icon' => $icon,
                'width' => $width,
                'height' => $height,
                'root_class' => $root_class,
                'class' => $class,
                'svg' => $svg,
            ]
        );
    }

    /**
     * function getNotificationIcon
     *
     * @param mixed $type
     * @param mixed $size
     *
     * @return mixed
     */
    public function getNotificationIcon($type, $size = "20")
    {
        $icon = match ($type) {
            'primary' => 'info',
            'secondary' => 'info',
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'error',
            default => 'info', // fallback
        };

        return $this->getIcon($icon, $size);
    }

    /**
     * function smallUploadBox
     * 
     * legacy function used by finalium and bootstrap skin only.
     * apparantly this is used on finalium for Some reason.
     *
     * @param mixed $upload
     *
     * @return mixed
     */
    public function smallUploadBox($upload)
    {
        return $this->twig->render('components/smallvideobox.twig', ['data' => $upload]);
    }

    /**
     * function comment
     *
     * legacy function used by finalium and bootstrap skin only.
     * apparantly this is used on finalium for Some reason.
     * 
     * @param mixed $comment
     *
     * @return mixed
     */
    public function comment($comment)
    {
        return $this->twig->render('components/comment.twig', ['comment' => $comment]);
    }
    //

    /**
     * function localize
     *
     * @param mixed $key
     * @param mixed $args
     *
     * @return mixed
     */
    public function localize($key, ...$args)
    {
        return $this->sb->getLocalizationClass()->translate($key, ...$args);
    }

    /**
     * function getUserDataCache
     *
     * @return array
     */
    public function getUserDataCache(): array
    {
        return UserData::getCache();
    }
}
