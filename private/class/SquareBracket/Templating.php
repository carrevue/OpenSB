<?php

namespace SquareBracket;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\DebugExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * A rewrite of openSB's /private/layout.php.
 */
class Templating
{
    private $skin;
    private $theme;
    private FilesystemLoader $loader;
    private Environment $twig;

    /**
     * @throws LoaderError
     */
    public function __construct(SquareBracket $orange)
    {
        global $isChazizSB, $auth, $isDebug, $branding, $enableInviteKeys, $enableCache;
        chdir(SB_PRIVATE_PATH);

        $options = $orange->getLocalOptions();

        $this->skin = $options["skin"] ?? "biscuit";
        $this->theme = $options["theme"] ?? "default";

        //if ($this->skin === null || trim($this->skin) === '' || !is_dir('skins/' . $this->skin . '/templates')) {
        if ($this->skin === null || trim($this->skin) === '') {
            trigger_error("Currently selected skin is invalid", E_USER_WARNING);
            $this->skin = "biscuit";
        }

        $skinPath = 'skins/' . $this->skin;

        // get metadata so that we can check if the skin is actually intended for squarebracket
        $metadata = $this->getSkinMetadata($skinPath);

        // if this skin is not meant for squarebracket, don't load.
        if ($metadata["metadata"]["site"] != "squarebracket") {
            trigger_error("Currently selected skin is invalid", E_USER_WARNING);
            $this->skin = "biscuit";
        }

        $templatePath = $skinPath . '/templates';

        // if this skin isnt an actual skin, don't load.
        try {
            $this->loader = new FilesystemLoader($templatePath);
        } catch (LoaderError) {
            trigger_error("Currently selected skin is invalid", E_USER_WARNING);

            $this->skin = "biscuit";
            $this->theme = "default";
            $templatePath = "skins/biscuit/templates";
            $this->loader = new FilesystemLoader($templatePath);
        }

        $doCache = !$enableCache ? false : 'skins/cache/';

        $this->loader->addPath('skins/common/');
        $this->twig = new Environment($this->loader, ['debug' => $isDebug, 'cache' => $doCache]);

        $this->twig->addFunction(new TwigFunction('component', function($component) use ($templatePath) {
            $path = '/components/' . $this->theme . '/' . $component . '.twig';
            $path_default = '/components/default/' . $component . '.twig';

            if (file_exists(SB_PRIVATE_PATH . '/' . $templatePath . $path)) {
                return $path;
            } elseif (file_exists(SB_PRIVATE_PATH . '/' . $templatePath . $path_default)) {
                return $path_default;
            } else {
                return '/missing_component.twig';
            }
        }));

        $this->twig->addExtension(new SquareBracketTwigExtension($orange, $this->twig));
        $this->twig->addExtension(new StringExtension());

        if ($isDebug) {
            $this->twig->addExtension(new DebugExtension());
        } else {
            $this->twig->addFunction(new TwigFunction('dump', function() {
                return "This function is not available outside of debug mode.";
            }));
        }

        // override squarebracket branding with fulptube branding if accessed via fulptube.rocks.
        // this fulptube branding is meant to look like the squarebracket branding on purpose, since
        // both squarebracket.pw and fulptube.rocks lead to the same site.
        if (Utilities::isFulpTube()) {
            $isFulpTube = true;
            $branding = [
                "name" => "FulpTube",
                "assets_location" => "/assets/sb_branding/fulp",
            ];
        } else {
            $isFulpTube = false;
            // custom branding for themes. for that Extra Accuracy™.
            if ($isChazizSB) {
                if ($this->skin == "finalium" && $this->theme == "beta") {
                    $branding["name"] = "cheeseRox";
                } elseif ($this->skin == "finalium" || $this->skin == "bootstrap") {
                    $branding["name"] = "squareBracket";
                }
            }
        }

        // TODO: make this dynamically changeable through the admin panel.
        $warningBannerTextIfOnChazizOwnedDomain = $branding["name"] . " is back online. Registrations are closed until
        the site is ready.";

        if ($isChazizSB) {
            $showWarningBanner = true;
            $warningBannerText = $warningBannerTextIfOnChazizOwnedDomain;
        } else {
            $showWarningBanner = false;
            $warningBannerText = null;
        }

        $versionNumber = new VersionNumber;

        $this->twig->addGlobal('is_chaziz_sb', $isChazizSB);
        $this->twig->addGlobal('is_fulptube', $isFulpTube);
        $this->twig->addGlobal('is_debug', $isDebug);
        $this->twig->addGlobal('is_user_logged_in', $auth->isUserLoggedIn());
        $this->twig->addGlobal('user_data', $auth->getUserData());
        $this->twig->addGlobal('user_ban_data', $auth->getUserBanData());
        $this->twig->addGlobal('user_notice_data', "DEPRECATED"); // DEPRECATED
        $this->twig->addGlobal('user_stat_data', $auth->getUserStatData());
        $this->twig->addGlobal('user_is_admin', $auth->isUserAdmin());
        $this->twig->addGlobal('user_is_authenticated_admin', $auth->hasUserAuthenticatedAsAnAdmin());
        $this->twig->addGlobal('skins', $this->getAllSkinsMetadata());
        $this->twig->addGlobal('opensb_version', $versionNumber->getVersionNumber());
        $this->twig->addGlobal('session', $_SESSION);
        $this->twig->addGlobal('website_branding', $branding);
        $this->twig->addGlobal('current_theme', $this->theme); // not to be confused with skins
        $this->twig->addGlobal('invite_keys_enabled', $enableInviteKeys);
        $this->twig->addGlobal('items_per_page', 20);
        // shit
        $this->twig->addGlobal('current_skin_and_theme', $this->skin . ',' . $this->theme); // move this shit
        $this->twig->addGlobal('current_skin', $this->skin);
        $this->twig->addGlobal('show_warning_banner', $showWarningBanner);
        $this->twig->addGlobal('warning_banner_text', $warningBannerText);
        $this->twig->addGlobal('is_opensb_v1_3', true);
        $this->twig->addGlobal('options', $options);

        /*
        if ($this->skin == "finalium" && $this->theme == "beta")
        {
            $db = $orange->getDatabase();
            $footerstats = $db->fetch("SELECT (SELECT COUNT(*) FROM users) users, (SELECT COUNT(*) FROM uploads) submissions");
            $this->twig->addGlobal('footer_stats', $footerstats);
        }
        */

        if (isset($_SERVER["REQUEST_URI"])) {
            $uri = explode('?', $_SERVER["REQUEST_URI"])[0];

            $uriParts = explode('/', trim($uri, '/'));

            // fix for admin panel tabs
            if (!empty($uriParts) && $uriParts[0] === 'admin') {
                $pageName = $uriParts[1] ?? 'overview';
            } else {
                $pageName = $uriParts[0] ?? 'index';
            }

            $this->twig->addGlobal('page_name', $pageName);
        }


        if (isset($_SERVER['HTTP_HOST'])) {
            $this->twig->addGlobal("page_url", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
            $this->twig->addGlobal("domain", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/");
        }
    }

    /**
     * Get all the available skins.
     *
     *
     * @return string[]
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
     * Get the skin's JSON metadata.
     *
     *
     * @param $skin
     * @return array|null
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

    public function getAllSkinsMetadata(): array
    {
        global $isDebug;
        $skins = [];
        foreach($this->getAllSkins() as $skin) {
            $metadata = $this->getSkinMetadata($skin);
            $site = $metadata["metadata"]["site"] ?? "unknown";
            if ($site == "squarebracket") {
                $incomplete = $isDebug ? false : ($metadata["metadata"]["incomplete"] ?? false);
                // dont show incomplete skins
                if (!$incomplete) {
                    // dont show incomplete themes
                    if (isset($metadata["metadata"]["themes"]) && is_array($metadata["metadata"]["themes"])) {
                        $metadata["metadata"]["themes"] = array_filter($metadata["metadata"]["themes"], function($theme)
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
     *
     * @param $template
     * @param array $data
     * @return string
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     */
    public function render($template, array $data = []): string
    {
        return $this->twig->render($template, $data);
    }
}

