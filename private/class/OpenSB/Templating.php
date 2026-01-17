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

use OpenSB\UserRoleEnum;

use OpenSB\Utilities;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * class Templating
 *
 * The Twig wrapper
 */
class Templating
{
    /**
     * @var string The current user's skin
     */
    private string $skin;

    /**
     * @var string The current user's theme
     */
    private string $theme;

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

        $options = $sb->getLocalOptions();

        $default_skin = "trinium";
        $default_theme = "default";

        if ($this->sb->isFulpTubeMode()) {
            $default_skin = "finalium";
            $default_theme = "hitchhiker";
        }

        $this->skin = $options["skin"] ?? $default_skin;
        $this->theme = $options["theme"] ?? $default_theme;

        //if ($this->skin === null || trim($this->skin) === '' || !is_dir('skins/' . $this->skin . '/templates')) {
        if ($this->skin === null || trim($this->skin) === '') {
            trigger_error("Current skin is invalid", E_USER_WARNING);
            $this->skin = "trinium";
        }

        $skinPath = 'skins/' . $this->skin;

        // get metadata so that we can check if the skin is actually intended for squarebracket
        $metadata = $this->getSkinMetadata($skinPath);

        // if this skin is not meant for squarebracket, don't load.
        if ($metadata["metadata"]["site"] != "squarebracket") {
            trigger_error("Current skin is invalid", E_USER_WARNING);
            $this->skin = "trinium";
        }

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            trigger_error("Current skin is invalid", E_USER_WARNING);

            $this->skin = "trinium";
            $this->theme = "default";
            $templatePath = "skins/trinium/templates";
            $this->loader = new FilesystemLoader($templatePath);
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

        $this->twig->addExtension(new SquareBracketTwigExtension($sb, $this->twig));
        $this->twig->addExtension(new StringExtension());

        if ($sb->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function () {
                trigger_error("Twig dump function called outside of debug mode!", E_USER_WARNING);
                return "This function is not available outside of debug mode.";
            }));
        }

        $isFulpTubeMode = $sb->isFulpTubeMode();
        $branding = $sb->getBrandingSettings();

        // TODO: make this dynamically changeable through the admin panel.
        $warningBannerTextIfOnChazizOwnedDomain = $branding["name"] . " is currently in a testing phase.
        Registrations are closed until the site is ready.";

        if ($sb->isTestInstance()) {
            $showWarningBanner = true;
            $warningBannerText = $warningBannerTextIfOnChazizOwnedDomain;
        } else {
            $showWarningBanner = false;
            $warningBannerText = null;
        }

        //$this->version_number = $sb->getVersionNumberClass();
        $this->version_number = new VersionNumber();

        // TODO: this should be cleaned up on 2.1 or maybe 3.0
        $this->twig->addGlobal('is_chaziz_sb', $sb->isChazizInstance());
        $this->twig->addGlobal('is_test_instance', $sb->isTestInstance());
        $this->twig->addGlobal('is_fulptube', $isFulpTubeMode);
        $this->twig->addGlobal('is_debug', $sb->isDebug());
        $this->twig->addGlobal('is_user_logged_in', $this->authentication->isUserLoggedIn());
        $this->twig->addGlobal('user_data', $this->authentication->getUserData());
        $this->twig->addGlobal('user_ban_data', $this->authentication->getUserBanData());
        $this->twig->addGlobal('user_stat_data', $this->authentication->getUserStatData());
        $this->twig->addGlobal('user_is_authenticated_admin', $this->authentication->hasUserAuthenticatedAsStaff());
        $this->twig->addGlobal('skins', $this->getAllSkinsMetadata());
        $this->twig->addGlobal('opensb_version', $this->version_number->getVersionArray());
        $this->twig->addGlobal('session', $_SESSION);
        $this->twig->addGlobal('website_branding', $branding);
        $this->twig->addGlobal('current_theme', $this->theme); // not to be confused with skins
        $this->twig->addGlobal('invite_keys_enabled', $sb->isInviteKeysEnabled());
        $this->twig->addGlobal('items_per_page', 20);
        $this->twig->addGlobal('current_skin', $this->skin);
        $this->twig->addGlobal('show_warning_banner', $showWarningBanner);
        $this->twig->addGlobal('warning_banner_text', $warningBannerText);
        $this->twig->addGlobal('options', $options);
        $this->twig->addGlobal('language_code', $this->sb->getLocalizationClass()->getLanguageCode());
        $this->twig->addGlobal('is_goanna', $this->areWeOnGoanna());
        $this->twig->addGlobal('csrf_token', $_SESSION['csrf_token']);

        if (isset($_SERVER["REQUEST_URI"])) {
            $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            $uriParts = array_filter(explode('/', trim($uri, '/')));

            $pageName = match ($uriParts[0] ?? '') {
                'user'      => 'user ' . ($uriParts[1] ?? ''),
                'dashboard' => 'dashboard-' . ($uriParts[1] ?? 'home'),
                'index', '' => 'home',
                default     => $uriParts[0] ?? 'index'
            };

            $this->twig->addGlobal('page_name', $pageName);
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $this->twig->addGlobal("page_url", Utilities::getURL(true));
            $this->twig->addGlobal("domain", Utilities::getURL(false));
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
        $excludedSkins = ['common', 'cache', 'error'];

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
        if (file_exists($skin . "/skin.json")) {
            $metadata = file_get_contents($skin . "/skin.json");
        } else {
            trigger_error(sprintf("The metadata for OpenSB skin %s is missing", $skin), E_USER_WARNING);
            return null;
        }
        return json_decode($metadata, true);
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
            $site = $metadata["metadata"]["site"] ?? "unknown";
            if ($site == "squarebracket") {
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
        }

        // sort by metadata name
        usort($skins, function ($a, $b) {
            return strcmp($a["metadata"]["name"], $b["metadata"]["name"]);
        });

        return $skins;
    }

    /**
     * function render
     *
     * @param mixed $template
     * @param array $data
     *
     * @return string
     */
    public function render($template, array $data = []): string
    {
        return $this->twig->render($template, $data);
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
}
