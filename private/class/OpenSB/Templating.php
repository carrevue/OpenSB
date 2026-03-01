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

namespace OpenSB;

use OpenSB\Utilities;
use OpenSB\SpfDOMExtractor;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Extension\DebugExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

use RuntimeException;

/**
 * class Templating
 *
 * The Twig wrapper
 */
class Templating
{
    /**
     * @var array The user's options
     */
    private array $options;
    /**
     * @var string The current skin
     */
    private string $skin;

    /**
     * @var string The current theme
     */
    private string $theme;

    /**
     * @var boolean If this is a SPF request.
     */
    private bool $is_spf;

    /**
     * @var array The icon map used on the Finalium skin.
     */
    private array $finalium_icon_map;

    /**
     * @var SquareBracket The core SquareBracket class
     */
    private SquareBracket $sb;

    /**
     * @var Authentication The authentication class
     */
    private Authentication $authentication;

    /**
     * @var FilesystemLoader Twig's Filesystem Loader
     */
    private FilesystemLoader $loader;

    /**
     * @var Environment The Twig environment
     */
    private Environment $twig;

    /**
     * @var VersionNumber The version number class
     */
    private VersionNumber $version_number;

    /**
     * @var SquareBracketTwigExtension The twig extension
     */
    private SquareBracketTwigExtension $twigExtension;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return mixed|string
     */
    public function __construct(SquareBracket $sb)
    {
        chdir(SB_PRIVATE_PATH);

        $this->sb = $sb;
        $this->authentication = $this->sb->getAuthenticationClass();

        $this->options = $sb->getLocalOptions();

        $default_skin = "trinium";
        $default_theme = "default";

        if ($this->sb->isFulpTubeMode()) {
            $default_skin = "finalium";
            $default_theme = "hitchhiker";
        }

        $this->is_spf = $this->sb->isSpfRequest();

        $this->skin = $this->options["skin"] ?? $default_skin;
        $this->theme = $this->options["theme"] ?? $default_theme;

        //if ($this->skin === null || trim($this->skin) === '' || !is_dir('skins/' . $this->skin . '/templates')) {
        if ($this->skin === null || trim($this->skin) === '') {
            /*trigger_error("Current skin is invalid", E_USER_WARNING);
            $this->skin = "trinium";*/
            $this->resetToDefault();
        }

        $skinPath = 'skins/' . $this->skin;

        // load in the skin metadata
        $metadata = $this->getSkinMetadata($skinPath);
        if (!$metadata) {
            /*trigger_error("Failed to load skin", E_USER_WARNING);
            $this->skin = "trinium";*/
            $this->resetToDefault();
        }

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            /*
            trigger_error("Failed to load skin", E_USER_WARNING);

            $this->skin = "trinium";
            $this->theme = "default";
            $templatePath = "skins/trinium/templates";
            $this->loader = new FilesystemLoader($templatePath);
            */
            $this->resetToDefault();
        }

        $doCache = !$sb->isTemplateCachingEnabled() ? false : 'skins/cache/';

        $this->loader->addPath('skins/common/');
        $this->twig = new Environment($this->loader, ['debug' => $sb->isDebug(), 'cache' => $doCache]);

        $this->twig->addFunction(new TwigFunction('component', function ($component) use ($templatePath) {
            $path = '/components/' . $this->theme . '/' . $component . '.twig';
            $path_default = '/components/default/' . $component . '.twig';
            $path_common = 'skins/common/' . $component . '.twig';

            if (file_exists(SB_PRIVATE_PATH . '/' . $templatePath . $path)) {
                return $path;
            } elseif (file_exists(SB_PRIVATE_PATH . '/' . $templatePath . $path_default)) {
                return $path_default;
            } elseif (file_exists(SB_PRIVATE_PATH . '/' . $path_common)) {
                return $component . '.twig'; // i guess???
            } else {
                return '/missing_component.twig';
            }
        }));

        $isFulpTubeMode = $sb->isFulpTubeMode();
        $branding = $sb->getBrandingSettings();

        // TODO: make this dynamically changeable through the admin panel.
        $showWarningBanner = false;
        $warningBannerText = null;

        if ($this->authentication->isUserLoggedIn() && 
            $this->authentication->getUserFlags() & UserFlags::FLAG_UNVERIFIED->value) {
            $localization = $sb->getLocalizationClass();

            $showWarningBanner = true;
            $warningBannerText = sprintf(
                '%s <a href="/verify_email?resend">%s</a>',
                $localization->translate('heads_up'),
                $localization->translate('heads_up_link')
            );
        }

        $this->version_number = new VersionNumber();

        // TODO: this should be cleaned up on 2.1 or maybe 3.0
        $this->twig->addGlobal('is_chaziz_sb', $sb->isChazizInstance());
        $this->twig->addGlobal('is_test_instance', $sb->isTestInstance());
        $this->twig->addGlobal('is_fulptube', $isFulpTubeMode);
        $this->twig->addGlobal('is_debug', $sb->isDebug());
        $this->twig->addGlobal('is_user_logged_in', $this->authentication->isUserLoggedIn());
        $this->twig->addGlobal('user_data', $this->authentication->getUserData());
        $this->twig->addGlobal('user_stat_data', $this->authentication->getUserStatData());
        $this->twig->addGlobal('user_is_authenticated_admin', $this->authentication->hasUserAuthenticatedAsStaff());
        $this->twig->addGlobal('skins', $this->getAllSkinsMetadata());
        $this->twig->addGlobal('opensb_version', $this->version_number->getVersionArray());
        $this->twig->addGlobal('session', $_SESSION);
        $this->twig->addGlobal('website_branding', $branding);
        $this->twig->addGlobal('current_theme', $this->theme); // the current skin in the current theme
        $this->twig->addGlobal('invite_keys_enabled', $sb->isInviteKeysEnabled());
        $this->twig->addGlobal('items_per_page', 20); // principia-web leftover, probably remove this shit
        $this->twig->addGlobal('current_skin', $this->skin);
        $this->twig->addGlobal('show_warning_banner', $showWarningBanner);
        $this->twig->addGlobal('warning_banner_text', $warningBannerText);
        $this->twig->addGlobal('options', $this->options);
        $this->twig->addGlobal('language_code', $this->sb->getLocalizationClass()->getLanguageCode());
        $this->twig->addGlobal('enable_incomplete_features', $this->sb->isIncompleteFeaturesEnabled());
        $this->twig->addGlobal('is_spf', $sb->isSpfRequest());
        $this->twig->addGlobal('is_goanna', $this->areWeOnGoanna());

        if ($this->skin == "finalium") {
            // fi = finalium icon
            $this->generateFinaliumIconMap();
            $this->twig->addGlobal('fi', $this->finalium_icon_map);
        }

        if (isset($_SERVER["REQUEST_URI"])) {
            $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            $uriParts = array_filter(explode('/', trim($uri, '/')));

            $pageName = match ($uriParts[0] ?? '') {
                'user'      => 'user ' . ($uriParts[1] ?? ''),
                'dashboard' => 'dashboard-' . ($uriParts[1] ?? 'home'),
                'index', '' => 'home',
                default     => $uriParts[0] ?? 'home'
            };

            $this->twig->addGlobal('page_name', $pageName);
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            if ($sb->isIpLookupEnabled() && isset($_SESSION['ip_country_code'])) {
                $this->twig->addGlobal('country_code', $_SESSION['ip_country_code'] ?? '');
            }

            $this->twig->addGlobal('page_url', Utilities::getURL(true));
            $this->twig->addGlobal('domain', Utilities::getURL(false));
        }

        $this->twigExtension = new SquareBracketTwigExtension($sb, $this->twig);

        $this->twig->addExtension($this->twigExtension);
        $this->twig->addExtension(new StringExtension());

        if ($sb->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function () {
                trigger_error("Twig dump function called outside of debug mode!", E_USER_WARNING);
                return "This function is not available outside of debug mode.";
            }));
        }
    }

    /**
     * function getFinaliumIconMap
     *
     * Gets the Finalium icon map.
     *
     * @return array
     */
    public function getFinaliumIconMap(): array
    {
        if ($this->skin == "finalium") {
            return $this->finalium_icon_map ?? [];
        } else {
            throw new RuntimeException("getFinaliumIconMap() called when the current skin isn't Finalium");
        }
    }

    /**
     * function getAllSkins
     *
     * Get all the available skins.
     *
     * @return array
     */
    public function getAllSkins(): array
    {
        $skins = [];

        // stuff in the skins folder that arent Proper skins
        $excludedSkins = ['common', 'cache', 'error', 'mail'];

        // include currently installed skins
        foreach (glob('skins/*', GLOB_ONLYDIR) as $skin) {
            $skinName = basename($skin);

            if (!in_array($skinName, $excludedSkins)) {
                $skins[] = $skin;
            }
        }

        return $skins;
    }

    /**
     * function getSkinMetadata
     *
     * Get the skin's JSON metadata.
     *
     * @param mixed $skin
     *
     * @return array
     */
    public function getSkinMetadata($skin): ?array
    {
        $skin_name = substr($skin, strrpos($skin, '/') + 1);

        if (file_exists($skin . "/skin.json")) {
            $metadata = file_get_contents($skin . "/skin.json");

            if ($metadata === false) {
                trigger_error(sprintf("Could not read the metadata for skin %s", $skin_name), E_USER_WARNING);
                return null;
            }

            $metadata = json_decode($metadata, true);

            // awkward leftover from the opensb 1.2 beta era, when there was
            // gonna be a secondary site to sb called "soos" which died in
            // very early development.
            if (isset($metadata["site"]) && $metadata["site"] !== "squarebracket") {
                trigger_error(sprintf("%s is incompatible", $skin_name), E_USER_WARNING);
                return null;
            }

            // anti-stupid check
            if (isset($metadata["requires"]["MediaWiki"])) {
                trigger_error(sprintf("%s is incompatible", $skin_name), E_USER_WARNING);
                return null;
            }

            /*
            // version check (very crude for now)
            if (!isset($metadata["version"])) {
                trigger_error(sprintf("%s is too old", $skin_name), E_USER_WARNING);
                return null;
            }
            */

            return $metadata;
        } else {
            trigger_error(sprintf("The metadata for skin %s is missing", $skin_name), E_USER_WARNING);
            return null;
        }
    }

    /**
     * function getAllSkinsMetadata
     * 
     * Get all installed skins' JSON metadata.
     *
     * @return array
     */
    public function getAllSkinsMetadata(): array
    {
        // kinda ugly but if i dont do this then it fucks up
        $isDebug = $this->sb->isDebug();

        $skins = [];
        foreach ($this->getAllSkins() as $skin) {
            $metadata = $this->getSkinMetadata($skin);

            $incomplete = $this->sb->isDebug() ? false : ($metadata["metadata"]["incomplete"] ?? false);

            // dont show incomplete skins
            if (!$incomplete) {
                // dont show incomplete themes
                if (isset($metadata["metadata"]["themes"]) && is_array($metadata["metadata"]["themes"])) {
                    $metadata["metadata"]["themes"] = array_filter($metadata["metadata"]["themes"], function ($theme)
                    use ($isDebug) {
                        return $isDebug || !($theme["incomplete"] ?? false);
                    });
                }
                $skins[] = $metadata;
            }
        }

        // sort by metadata name
        usort($skins, function ($a, $b) {
            return strcmp($a["metadata"]["name"], $b["metadata"]["name"]);
        });

        return $skins;
    }

    /**
     * function setPageMeta
     * 
     * Sets the page meta.
     *
     * @param array $data The data
     *
     * @return string
     */
    public function setPageMeta(array $data = []): void
    {
        $meta = [];

        foreach ($data as $key => $value) {
            $meta[$key] = $value;
        }

        $this->twig->addGlobal('meta', $meta);
    }

    /**
     * function render
     * 
     * Renders the template.
     *
     * @param string $template The template
     * @param array $data The data
     *
     * @return string
     */
    public function render($template, array $data = []): string
    {
        $twig_output = $this->twig->render($template, $data);

        if ($this->is_spf /*|| true*/) {
            return $this->outputSpfJson($twig_output);
        } else {
            return $twig_output;
        }
    }

    /**
     * function outputSpfJson
     *
     * Takes the Twig HTML output, and parses it into JSON compatible with
     * SPF (Structured Page Fragments).
     * 
     * @note This is currently hardcoded for Finalium
     *
     * @return bool
     */
    private function outputSpfJson($input): string {
        header('Content-Type: application/json');

        $extractor = new SpfDOMExtractor($input);

        $url = Utilities::getURL(true);
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $params);
        unset($params['spf']);

        $output_url = ($parts['path'] ?? '') . ($params ? '?' . http_build_query($params) : '');

        // render all required bits
        $spf_output = [
            "head" => $extractor->getResourceElementsFromTag("head"),
            "body" => [
                "precontent" => $extractor->getElementContentsFromID("precontent"),
                "content" => $extractor->getElementContentsFromID("content"),
            ],
            "url" => $output_url,
            "attr" => [
                "content" => [
                    "class" => $extractor->getElementClassesFromID("content"),
                ],
                "page" => [ // sb/finalium-specific quirk not found in real hitchhiker ?
                    "class" => $extractor->getElementClassesFromID("page"),
                ],
                "body" => [
                    "class" => $extractor->getElementClassesFromID("body"),
                ]
            ],
            "name" => "other",
            "title" => [$extractor->getTitle()],
        ];
            

        return json_encode($spf_output);
    }

    /**
     * function areWeOnGoanna
     *
     * to some, the goanna-based pale moon and basilisk web browsers are an 
     * accessible way to have the "classic firefox experience" without having 
     * to fuck around with custom firefox userchrome themes. however, certain 
     * things in the goanna engine are implemented in ways that arent consistent 
     * with the mainline engines, so we gotta do this to enable certain css 
     * workarounds within trinium/finalium without potentially causing issues 
     * elsewhere.
     *
     * @return bool
     */
    private function areWeOnGoanna(): bool {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) { return false; }
        if (str_contains($_SERVER['HTTP_USER_AGENT'], "Goanna/")) { return true; }
        return false;
    }

    /**
     * function resetToDefault
     * 
     * Resets the current theme to Trinium Default and then attempts to refresh.
     * 
     * @note On FulpTube mode, this should stay as is.
     * 
     * @return void
     */
    private function resetToDefault(): void {
        $this->options["skin"] = "trinium";
        $this->options["theme"] = "default";

        $this->sb->setOptionCookie($this->options);
        header("Refresh: 0");
        die();
    }

    /**
     * function generateFinaliumIconMap
     * 
     * This defines a list of icons depending on which Finalium theme is being used.
     * 
     * @return void
     */
    private function generateFinaliumIconMap(): void {
        if ($this->skin != "finalium") {
            throw new RuntimeException("generateFinaliumIconMap() called when the current skin isn't Finalium");
        }

        if ($this->theme == "hitchhiker") {
            $this->finalium_icon_map = [
                'account_settings' => 'account-settings',
                'alert_info' => 'alert-info',
                'alert_warning' => 'alert-warning',
                'button_follow' => 'button-follow',
                'button_followed' => 'button-followed',
                'button_unfollow' => 'button-unfollow',
                'guide_home' => 'guide-home',
                'guide_uploads' => 'guide-uploads',
                'guide_browse_members' => 'guide-browse-members',
                'guide_my_profile' => 'guide-my-profile',
                'guide_collection' => 'guide-collection',
                'masthead_guide' => 'masthead-guide',
                'masthead_search' => 'masthead-search',
                'masthead_upload' => 'masthead-upload',
                'masthead_bell' => 'masthead-bell',
                'spinner_left' => 'spinner-left',
                'spinner_right' => 'spinner-right',
                'userlink_staff' => 'userlink-staff',
                'watch_like' => 'watch-like',
                'watch_dislike' => 'watch-dislike',
                'watch_add_to' => 'watch-add-to',
                'watch_share' => 'watch-share',
                'watch_more' => 'watch-more',
                'watch_panel_report' => 'watch-panel-report',
                'watch_panel_stats' => 'watch-panel-stats',
                'watch_panel_dismiss' => 'watch-panel-dismiss',
                'watch_creator_info' => 'watch-creator-info',
            ];
        } else {
            $this->finalium_icon_map = [
                'account_settings' => 'gear-fill',
                'alert_info' => 'asterisk',
                'alert_warning' => 'exclamation-triangle',
                'button_follow' => 'plus-lg',
                'button_followed' => 'check-lg',
                'button_unfollow' => 'x-lg',
                'guide_home' => 'house-door',
                'guide_uploads' => 'play-btn',
                'guide_browse_members' => 'people',
                'guide_my_profile' => 'person',
                'guide_collection' => 'list-ul',
                'masthead_guide' => 'list',
                'masthead_search' => 'search',
                'masthead_upload' => 'upload',
                'masthead_bell' => 'bell',
                'spinner_left' => 'chevron-left',
                'spinner_right' => 'chevron-right',
                'userlink_staff' => 'shield',
                'watch_like' => 'hand-thumbs-up-fill',
                'watch_dislike' => 'hand-thumbs-down-fill',
                'watch_add_to' => 'plus-lg',
                'watch_share' => 'share-fill',
                'watch_more' => 'three-dots',
                'watch_panel_report' => 'flag-fill',
                'watch_panel_stats' => 'bar-chart-fill',
                'watch_panel_dismiss' => 'x-lg',
                'watch_creator_info' => 'pencil-fill',
            ];      
        }
    }
}
