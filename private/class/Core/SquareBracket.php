<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz
  Copyright (C) 2024 OkayHush

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

/**
 * class SquareBracket
 *
 * The core OpenSB class.
 */
class SquareBracket
{
    /**
     * @var Database
     */
    private Database $database;

    /**
     * @var Profiler
     */
    private Profiler $profiler;

    /**
     * @var Storage
     */
    private Storage $storage;

    /**
     * @var Authentication
     */
    private Authentication $authentication;

    /**
     * @var Localization
     */
    private Localization $localization;

    /**
     * @var DiscordWebhookLogging
     */
    private ?DiscordWebhookLogging $discord;

    /**
     * @var IPLookup
     */
    private ?IPLookup $ip_lookup;

    /**
     * @var Mail
     */
    private ?Mail $mail;

    /**
     * @var Templating
     */
    private Templating $templating;

    /**
     * @var bool
     */
    private bool $is_debug = false;

    /**
     * @var bool
     */
    private bool $is_chaziz_instance = false;

    /**
     * @var bool
     */
    private bool $is_test_instance = false;

    /**
     * @var bool
     */
    private bool $template_caching_enabled = false;

    /**
     * @var bool
     */
    private bool $under_maintenance = false;

    /**
     * @var bool
     */
    private bool $enable_account_registration = true;

    /**
     * @var bool
     */
    private bool $enable_invite_keys = false;

    /**
     * @var bool
     */
    private bool $enable_lockdown = false;

    /**
     * @var bool
     */
    private bool $enable_discord_webhook = false;

    /**
     * @var bool
     */
    private bool $enable_ip_lookup = false;

    /**
     * @var bool
     */
    private bool $enable_mail = false;

    /**
     * @var bool
     */
    private bool $disable_assets = false;

    /**
     * @var bool
     */
    private bool $enable_mature_uploads = false;

    /**
     * @var array
     */
    private array $branding_settings;

    /**
     * @var array
     */
    private array $captcha_settings;

    /**
     * @var array
     */
    public array $options;

    /**
     * @var array
     */
    private string $skin = "trinium";

    /**
     * @var array
     */
    private string $theme = "default";

    /**
     * @var array
     */
    private array $skinInfo;

    /**
     * @var array
     */
    private array $themeInfo;

    /**
     * @var array
     */
    private array $skinThemeOptions;

    /**
     * @var array
     */
    private array|null $accounts = [];

    /**
     * @var string
     */
    private string $accounts_cookie_warning = "DO-NOT-SHARE-THIS-WITH-ANYONE-";

    /**
     * function __construct
     *
     * Initialize the core OpenSB classes.
     *
     * @param mixed $config
     *
     * @return void
     */
    public function __construct($config)
    {
        // extract settings
        $host = $config["mysql"]["host"];
        $db = $config["mysql"]["database"];
        $user = $config["mysql"]["username"];
        $pass = $config["mysql"]["password"];

        $allowedSites = ['default', 'test', 'chaziz'];
        if (!in_array($config["site"], $allowedSites)) {
            throw new \RuntimeException("The site mode in the configuration file should be 
            set either to default, test or chaziz.");
        }
        $this->is_chaziz_instance = ($config["site"] === "chaziz");

        $this->is_test_instance = (
            ($this->is_chaziz_instance && str_contains(Utilities::getURL(), "web-orange-qa"))
            || ($config["site"] === "test")
        );

        if ($this->is_chaziz_instance) {
            // gets overriden by options, but keep this as a fallback.
            date_default_timezone_set('America/New_York');
        }

        $this->is_debug = ($config["mode"] ?? '') === "DEV";

        $this->database = new Database($host, $user, $pass, $db);
        $this->authentication = new Authentication($this, $_SESSION["SBTOKEN"] ?? null);

        if (
            $this->is_test_instance &&
            $this->authentication->isUserLoggedIn() &&
            $this->authentication->hasUserAuthenticatedAsStaff()
        ) {
            $this->is_debug = true;
        }

        $this->profiler = new Profiler($this->database);
        if ($this->is_debug) {
            // enable db profiler (not to be confused with the other profiler)
            // if we are on debug mode
            $this->database->setProfiling(true);
        }

        //$this->version_number = new VersionNumber();

        $this->options = [];

        if (isset($_COOKIE["SBOPTIONS"])) {
            $this->options = $this->getOptionsCookie();

            if (!empty($this->options['timezone'])
                && in_array($this->options['timezone'], timezone_identifiers_list(), true)
            ) {
                date_default_timezone_set($this->options['timezone']);
            }
        } else {
            // predefine these options
            $defaultSkin = "trinium";
            $defaultTheme = "default";

            if ($this->isFulpTubeMode()) {
                $defaultSkin = "finalium";
                $defaultTheme = "hitchhiker";
            }

            $this->options = [
                "skin" => $defaultSkin,
                "theme" => $defaultTheme,
                "locale" => "en-US",
            ];
            
            $this->setOptionCookie($this->options);
        }

        $this->skin = $this->options["skin"] ?? "trinium";
        $this->theme = $this->options["theme"] ?? "default";

        try {
            $this->skinInfo = new SkinInfo($this->skin)->getInfo();
        } catch (Exception $e) {
            if ($this->skin == "trinium") {
                http_response_code(500);
                die("The Trinium skin is not installed.");
            } elseif ($this->isFulpTubeMode() && $this->skin == "finalium") {
                trigger_error("The Finalium skin is not installed.", E_USER_WARNING);
            }

            $this->skin = "trinium";
            $this->theme = "default";
            $this->skinInfo = new SkinInfo($this->skin)->getInfo();
        }

        if (!isset($this->skinInfo["metadata"]["themes"][$this->theme])) {
            if ($this->theme == "default") {
                $this->skin = "trinium";
                $this->theme = "default";
                $this->skinInfo = new SkinInfo($this->skin);
            }

            $this->theme = "default";
        }

        $this->themeInfo = $this->skinInfo["metadata"]["themes"][$this->theme] ?? [];

        $this->skinThemeOptions = array_merge($this->skinInfo["metadata"]["options"] ?? [], $this->themeInfo["options"] ?? []);

        $storage_use_custom_path = (bool)($config['storage']['use_custom_path'] ?? false);
        $storage_path = $storage_use_custom_path
            ? ($config['storage']['custom_path'] ?? null)
            : null;

        $this->disable_assets = (bool)($config["disable_assets"] ?? false);
        $this->storage = new Storage($this, $storage_path);

        $this->enable_mature_uploads = (bool)($config["enable_mature_uploads"] ?? false);

        $this->captcha_settings = $config["captcha"];

        $this->template_caching_enabled = (bool)($config["cache"] ?? false);

        // TODO: port these into settings that can be changed through the dashboard
        $this->under_maintenance = (bool)($config["maintenance"] ?? false);
        $this->enable_account_registration = ($config["enable_registration"] ?? false);
        $this->enable_invite_keys = (bool)($config["invite_keys"] ?? false);
        $this->enable_lockdown = (bool)($config["lockdown"] ?? false);
        //

        $this->localization = new Localization($this->options);

        if (isset($_COOKIE["SBACCOUNTS"])) {
            $cookie_raw = $_COOKIE["SBACCOUNTS"];

            // get rid of warning string
            if (str_starts_with($cookie_raw, $this->accounts_cookie_warning)) {
                $cookie_raw = substr($cookie_raw, strlen($this->accounts_cookie_warning));
            }

            $decoded = Utilities::verifySignedCookiePayload($cookie_raw);

            if ($decoded !== false && is_array($decoded)) {
                $this->accounts = $decoded;
            } else {
                // if invalid, reset to empty array
                $this->accounts = [];
                Utilities::setSafeCookie(
                    'SBACCOUNTS',
                    $this->accounts_cookie_warning . Utilities::makeSignedCookiePayload([]),
                    time() + (30 * 24 * 60 * 60)
                );
            }
        } else {
            $this->accounts = [];
        }

        // override squarebracket branding with fulptube branding if accessed via fulptube.rocks.
        if ($this->isFulpTubeMode()) {
            //$isFulpTubeMode = true;
            $this->overrideBrandingWithFulpTube();
        } else {
            //$isFulpTubeMode = false;
            $this->branding_settings = [
                "name" => $config["branding"]["name"] ?? '',
                "assets_location" => $config["branding"]["assets"] ?? '',
                "is_vector" => $config["branding"]["is_vector"] ?? false,
                "use_wordmark" => $config["branding"]["use_wordmark"] ?? false,
            ];

            // use fulptube branding on sb if we're on finalium hitchhiker
            if ($this->is_chaziz_instance) {
                if ($this->options["skin"] == "finalium" && $this->options["theme"] == "hitchhiker") {
                    $this->overrideBrandingWithFulpTube();
                }
            }
        }

        $this->enable_discord_webhook = $config["discord_webhook"]["enabled"] ?? false;

        if ($this->enable_discord_webhook) {
            $this->discord = new DiscordWebhookLogging($this, $config["discord_webhook"]["url"]);
        } else {
            $this->discord = null;
        }

        $this->enable_ip_lookup = $config["ip_lookup"]["enabled"] ?? false;

        if ($this->enable_ip_lookup) {
            $this->ip_lookup = new IPLookup($config["ip_lookup"]["mmdb"]);

            if (empty($_SESSION['ip_country_code'])) {
                $_SESSION['ip_country_code'] = $this->ip_lookup->getCountry(Utilities::getIpAddress());
            }
        } else {
            $this->ip_lookup = null;
        }

        $this->enable_mail = $config["mail"]["enabled"] ?? false;

        if ($this->enable_mail) {
            $this->mail = new Mail($this, $config["mail"]);
        } else {
            $this->mail = null;
        }
        
        $this->templating = new Templating($this);
    }

    /**
     * function overrideBrandingWithFulpTube
     *
     * @return void
     */
    private function overrideBrandingWithFulpTube()
    {
        $path = ($this->is_test_instance || $this->isDebug())
            ? '/assets/sb_branding/fulp_qa'
            : '/assets/sb_branding/fulp';

        $this->branding_settings = [
            "name" => "FulpTube",
            "assets_location" => $path,
            "is_vector" => true,
            "use_wordmark" => true,
        ];
    }

    /**
     * function getOptionsCookie
     *
     * @return mixed|array
     */
    public function getOptionsCookie()
    {
        if (isset($_COOKIE['SBOPTIONS'])) {
            return json_decode(base64_decode($_COOKIE['SBOPTIONS']), true);
        } else {
            return [];
        }
    }

    /**
     * function setOptionCookie
     *
     * @param mixed $options
     *
     * @return void
     */
    public function setOptionCookie($options)
    {
        setcookie("SBOPTIONS", base64_encode(json_encode($options)), [
            'expires' => 2147483647,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax'
        ]);

        // overwrite our copy for certain cases (eg: changing the theme/language)
        $this->options = $options;
    }

    /**
     * function getCurrentSkinCssTimestamp
     * 
     * Returns timestamp of the current skin's theme.
     *
     * @return int 
     */
    public function getCurrentSkinCssTimestamp(string $skin = "", string $theme = ""): int
    {
        $skin  = $skin  ?: ($this->options["skin"]  ?? "trinium");
        $theme = $theme ?: ($this->options["theme"] ?? "default");
        $base  = SB_PUBLIC_PATH . "/assets/css";

        foreach (["-", "_"] as $sep) {
            $path = "{$base}/{$skin}{$sep}{$theme}.css";
            if (file_exists($path)) {
                return filemtime($path);
            }
        }

        return filemtime("{$base}/trinium-default.css");
    }

    /**
     * function getDatabaseClass
     *
     * Returns the database class for other classes to use.
     *
     * @return Database
     */
    public function getDatabaseClass(): Database
    {
        return $this->database;
    }

    /**
     * function getProfilerClass
     *
     * Returns the profiler class for other OpenSB classes to use.
     *
     * @return Profiler
     */
    public function getProfilerClass(): Profiler
    {
        return $this->profiler;
    }

    /**
     * function getStorageClass
     *
     * Returns the storage class for other OpenSB classes to use.
     *
     * @return Storage
     */
    public function getStorageClass(): Storage
    {
        return $this->storage;
    }

    /**
     * function getAuthenticationClass
     *
     * Returns the authentication class for other OpenSB classes to use.
     *
     * @return Authentication
     */
    public function getAuthenticationClass(): Authentication
    {
        return $this->authentication;
    }

    /**
     * function getLocalizationClass
     *
     * Returns the localization class for other OpenSB classes to use.
     *
     * @return Localization
     */
    public function getLocalizationClass(): Localization
    {
        return $this->localization;
    }

    /**
     * function isDiscordWebhookEnabled
     *
     * Returns the bool that toggles the Discord webhook logging class.
     *
     * @return bool
     */
    public function isDiscordWebhookEnabled(): bool
    {
        return $this->enable_discord_webhook;
    }

    /**
     * function getDiscordWebhookClass
     *
     * Returns the Discord webhook logging class.
     *
     * @return DiscordWebhookLogging
     */
    public function getDiscordWebhookClass(): DiscordWebhookLogging
    {
        if (!$this->discord || !$this->enable_discord_webhook) {
            throw new \RuntimeException("getDiscordWebhookClass() called while Discord webhook is disabled.");
        }
        return $this->discord;
    }

    /**
     * function isIpLookupEnabled
     *
     * Returns the bool that toggles the IP lookup class.
     *
     * @return bool
     */
    public function isIpLookupEnabled(): bool
    {
        return $this->enable_ip_lookup;
    }

    /**
     * function getIpLookupClass
     *
     * Returns the IP lookup class.
     *
     * @return IPLookup
     */
    public function getIpLookupClass(): IPLookup
    {
        if (!$this->ip_lookup || !$this->enable_ip_lookup) {
            throw new \RuntimeException("getIpLookupClass() called while IP reader is disabled.");
        }
        return $this->ip_lookup;
    }

    /**
     * function isMailEnabled
     *
     * Returns the bool that toggles the mail class.
     *
     * @return bool
     */
    public function isMailEnabled(): bool
    {
        return $this->enable_mail;
    }

    /**
     * function getMailClass
     *
     * Returns the mail class.
     *
     * @return Mail
     */
    public function getMailClass(): Mail
    {
        if (!$this->mail || !$this->enable_mail) {
            throw new \RuntimeException("getMailClass() called while IP reader is disabled.");
        }
        return $this->mail;
    }

    /**
     * function getTemplatingClass
     *
     * Returns the templating class.
     *
     * @return Templating
     */
    public function getTemplatingClass(): Templating
    {
        return $this->templating;
    }

    /**
     * function getLocalOptions
     *
     * Returns the user's local settings.
     *
     * @return array
     */
    public function getLocalOptions(): array
    {
        return $this->options;
    }

    /**
     * function getWarningString
     *
     * Returns warning string for accounts cookie.
     *
     * @return string
     */
    public function getWarningString(): string
    {
        return $this->accounts_cookie_warning;
    }

    /**
     * function getAccountsArray
     *
     * Returns array for changing accounts.
     *
     * @return array|string
     */
    public function getAccountsArray(): array|string
    {
        return $this->accounts ?? [];
    }

    /**
     * function getCurrentSkinName
     *
     * Returns current skin name
     *
     * @return string
     */
    public function getCurrentSkinName(): string
    {
        return $this->skin;
    }

    /**
     * function getCurrentThemeName
     *
     * Returns current theme name
     *
     * @return string
     */
    public function getCurrentThemeName(): string
    {
        return $this->theme;
    }

    /**
     * function getCurrentSkinInfo
     *
     * Returns array of the current skin.
     *
     * @return array
     */
    public function getCurrentSkinInfo(): array
    {
        return $this->skinInfo;
    }

    /**
     * function getCurrentSkinInfo
     *
     * Returns array of the current skin's current theme.
     *
     * @return array
     */
    public function getCurrentThemeInfo(): array
    {
        return $this->themeInfo;
    }

    /**
     * function getSkinThemeOptions
     *
     * Returns array of the current skin/theme's options.
     *
     * @return array
     */
    public function getSkinThemeOptions(): array
    {
        return $this->skinThemeOptions;
    }

    /**
     * function isDebug
     *
     * Returns boolean that indicates if debug is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->is_debug;
    }

    /**
     * function isIncompleteFeaturesEnabled
     *
     * Returns boolean that indicates if incomplete features should be enabled.
     * This is separate from isDebug, and is enabled by default on QA.
     *
     * @return bool
     */
    public function isIncompleteFeaturesEnabled(): bool
    {
        if ($this->is_test_instance) {
            return true;
        }

        if ($this->is_chaziz_instance && !$this->is_debug) {
            return false;
        }

        return $this->options['enable_incomplete_features'] ?? false;
    }

    /**
     * function isHitchhiker
     *
     * Returns boolean for if hitchhiker is enabled.
     * THIS IS SEPERATE FROM isFulpTubeMode()
     * 
     * @deprecated this will be removed in a future commit
     *
     * @return bool
     */
    public function isHitchhiker(): bool
    {
        return $this->skin === 'finalium'
            && $this->theme === 'hitchhiker';
    }

    /**
     * function isFulpTubeMode
     *
     * Returns boolean for FulpTube mode.
     *
     * @return bool
     */
    public function isFulpTubeMode(): bool
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }

        $isOnFulpTubeDomain = str_contains($_SERVER['HTTP_HOST'], 'fulptube.rocks') || $_SERVER['HTTP_HOST'] == "localhost-fulptube";

        $isDebugMode = ($this->options['debug_fulptube_branding'] ?? false) && ($this->isTestInstance() || $this->isDebug());

        if ($this->is_chaziz_instance && ($isOnFulpTubeDomain || $this->isHitchhiker() || $isDebugMode)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * function isTemplateCachingEnabled
     *
     * Returns boolean for enabling template caching.
     *
     * @return bool
     */
    public function isTemplateCachingEnabled(): bool
    {
        return $this->template_caching_enabled;
    }

    /**
     * function isAccountRegistrationEnabled
     *
     * Returns boolean for enabling account registration.
     *
     * @return bool
     */
    public function isAccountRegistrationEnabled(): bool
    {
        return $this->enable_account_registration;
    }

    /**
     * function isInviteKeysEnabled
     *
     * Returns boolean for enabling invite keys for account registration.
     *
     * @return bool
     */
    public function isInviteKeysEnabled(): bool
    {
        return $this->enable_invite_keys;
    }

    /**
     * function isLockdownEnabled
     *
     * Returns boolean for enabling lockdown.
     *
     * @return bool
     */
    public function isLockdownEnabled(): bool
    {
        return $this->enable_lockdown;
    }

    /**
     * function isUnderMaintenance
     *
     * Returns boolean for if the instance is under maintenance.
     *
     * @return bool
     */
    public function isUnderMaintenance(): bool
    {
        return $this->under_maintenance;
    }

    /**
     * function isAssetsDisabled
     *
     * Returns boolean for if assets are disabled. This will make Storage fallback to
     * the default placeholder thumbnails/profile pictures.
     *
     * @return bool
     */
    public function isAssetsDisabled(): bool
    {
        return $this->disable_assets;
    }

    /**
     * function isAssetsDisabled
     *
     * Returns boolean for if mature uploads are enabled.
     *
     * @return bool
     */
    public function isMatureUploadsEnabled(): bool
    {
        return $this->enable_mature_uploads;
    }

    /**
     * function isChazizInstance
     *
     * Returns a bool that indicates if the instance is set to "Chaziz" mode. this is the
     * mode used on squarebracket.pw and fulptube.rocks.
     *
     * @return bool
     */
    public function isChazizInstance(): bool
    {
        return  $this->is_chaziz_instance;
    }

    /**
     * function isTestInstance
     *
     * Returns a bool that indicates if the instance is set to "Test" mode. this is the
     * mode used on web-orange-qa.squarebracket.pw and web-orange-qa.fulptube.rocks.
     *
     * @return bool
     */
    public function isTestInstance(): bool
    {
        return $this->is_test_instance;
    }

    /**
     * function isSpfRequest
     *
     * Returns a bool that indicates if this is a SPF request.
     *
     * @return bool
     */
    public function isSpfRequest(): bool
    {
        return isset($_SERVER['HTTP_X_SPF_REQUEST']) || isset($_GET["spf"]);
    }

    /**
     * function getBrandingSettings
     *
     * Returns array for the instance's branding.
     *
     * @return array
     */
    public function getBrandingSettings(): array
    {
        return $this->branding_settings;
    }

    /**
     * function getCaptchaSettings
     *
     * Returns array for the captcha settings.
     *
     * @return array
     */
    public function getCaptchaSettings(): array
    {
        return $this->captcha_settings;
    }
}
