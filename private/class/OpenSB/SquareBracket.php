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

namespace OpenSB;

use OpenSB\Database;
use OpenSB\Profiler;

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
     * @var bool
     */
    private bool $is_debug = false;

    /**
     * @var bool
     */
    private bool $is_chaziz_squarebracket_instance = false;

    /**
     * @var bool
     */
    private bool $is_sitetest_instance = false;

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

        $allowedSites = ['squarebracket', 'squarebracket_chaziz', 'sitetest'];
        if (!in_array($config["site"], $allowedSites)) {
            trigger_error("The site mode in the configuration file should be 
            set either to squarebracket, squarebracket_chaziz or sitetest.", E_USER_WARNING);
        }
        $this->is_chaziz_squarebracket_instance = ($config["site"] === "squarebracket_chaziz");
        $this->is_sitetest_instance = ($config["site"] === "sitetest");

        $this->is_debug = ($config["mode"] ?? '') === "DEV";

        $this->database = new Database($host, $user, $pass, $db);
        $this->profiler = new Profiler($this->database);
        if ($this->is_debug) {
            // enable db profiler (not to be confused with the other profiler)
            // if we are on debug mode
            $this->database->setProfiling(true);
        }
        $this->authentication = new Authentication($this, $_SESSION["SBTOKEN"] ?? null);

        //$this->version_number = new VersionNumber();

        $this->storage = new Storage($this);

        $this->captcha_settings = $config["captcha"];

        $this->template_caching_enabled = (bool)($config["cache"] ?? false);

        // TODO: port these into settings that can be changed through the admin panel
        $this->under_maintenance = (bool)($config["maintenance"] ?? false);
        $this->enable_account_registration = ($config["enable_registration"] ?? false);
        $this->enable_invite_keys = (bool)($config["invite_keys"] ?? false);
        $this->enable_lockdown = (bool)($config["lockdown"] ?? false);
        //

        $this->options = [];

        // predefine these options
        $defaultSkin = "trinium";
        $defaultTheme = "default";

        if ($this->isFulpTube()) {
            $defaultSkin = "finalium";
            $defaultTheme = "hitchhiker";
        }

        $this->options = [
            "skin" => $defaultSkin,
            "theme" => $defaultTheme,
            "locale" => "en-US",
        ];

        if (isset($_COOKIE["SBOPTIONS"])) {
            $this->options = $this->getOptionsCookie();

            // the charla frontend is now called trinium
            if ($this->options["skin"] == "charla") {
                $this->options["skin"] = "trinium";
                $this->setOptionCookie($this->options);
            }

            // migrate biscuit users to trinium. the frontend has been retired.
            if ($this->options["skin"] == "biscuit") {
                $this->options["skin"] = "trinium";

                if ($this->options["theme"] == "soretro") {
                    $this->options["theme"] = "default";
                }

                $this->setOptionCookie($this->options);
                Utilities::notifyBanner("notify_frontend_no_longer_available", null, "primary", ["Biscuit"]);
            }
        } else {
            $this->setOptionCookie($this->options);
        }

        $this->localization = new Localization($this->options);

        if (isset($_COOKIE["SBACCOUNTS"])) {
            $cookie_raw = $_COOKIE["SBACCOUNTS"];

            // get rid of warning string
            if (strpos($cookie_raw, $this->accounts_cookie_warning) === 0) {
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
        if ($this->isFulpTube()) {
            //$isFulpTube = true;
            $this->overrideBrandingWithFulpTube();
        } else {
            //$isFulpTube = false;
            $this->branding_settings = [
                "name" => $config["branding"]["name"] ?? '',
                "assets_location" => $config["branding"]["assets"] ?? '',
                "is_vector" => $config["branding"]["is_vector"] ?? false,
                "use_wordmark" => $config["branding"]["use_wordmark"] ?? false,
            ];

            // custom branding for themes. for that Extra Accuracy™.
            // TODO: make finalium and bootstrap *actually* work with updated branding
            if ($this->is_chaziz_squarebracket_instance) {
                if ($this->options["skin"] == "finalium" && $this->options["theme"] == "hitchhiker") {
                    $this->overrideBrandingWithFulpTube();
                } elseif ($this->options["skin"] == "finalium" || $this->options["skin"] == "bootstrap") {
                    $this->branding_settings["name"] = "squareBracket";
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
        } else {
            $this->ip_lookup = null;
        }
    }

    /**
     * function logInWithToken
     * 
     * Reinitialize the authentication class with a specified token.
     * 
     * @note: This may cause issues in most use cases, and should only be used for the bot API.
     *
     * @return void
     */
    public function logInWithToken($token)
    {
        $this->authentication = new Authentication($this, $token);
    }

    /**
     * function overrideBrandingWithFulpTube
     *
     * @return void
     */
    private function overrideBrandingWithFulpTube()
    {
        $this->branding_settings = [
            "name" => "FulpTube",
            "assets_location" => "/assets/sb_branding/fulp",
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
            throw new \Exception("getDiscordWebhookClass() called while Discord webhook is disabled.");
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
            throw new \Exception("getIpLookupClass() called while IP reader is disabled.");
        }
        return $this->ip_lookup;
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
     * This is separate from isDebug, and is enabled by default on SiteTest.
     *
     * @return bool
     */
    public function isIncompleteFeaturesEnabled(): bool
    {
        if ($this->is_sitetest_instance) {
            return true;
        } else {
            return $this->options["enable_incomplete_features"] ?? false;
        }
    }

    /**
     * function isHitchhiker
     *
     * Returns boolean for if hitchhiker is enabled.
     * THIS IS SEPERATE FROM isFulpTube()
     *
     * @return bool
     */
    public function isHitchhiker(): bool
    {
        return ($this->getLocalOptions()['skin'] ?? '') === 'finalium'
            && ($this->getLocalOptions()['theme'] ?? '') === 'hitchhiker';
    }

    /**
     * function isFulpTube
     *
     * Returns boolean for FulpTube mode.
     *
     * @return bool
     */
    public function isFulpTube(): bool
    {
        if (!isset($_SERVER['HTTP_HOST'])) {
            return false;
        }

        $isOnFulpTubeDomain = str_contains($_SERVER['HTTP_HOST'], 'fulptube.rocks');

        $isDebugMode = ($this->getLocalOptions()['debug_fulptube_branding'] ?? false) && $this->isDebug();

        if ($this->isChazizSquareBracketInstance() && ($isOnFulpTubeDomain || $this->isHitchhiker() || $isDebugMode)) {
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
     * function isChazizSquareBracketInstance
     *
     * Returns a bool that indicates if the instance is set to "Chaziz SquareBracket" mode.
     *
     * @return bool
     */
    public function isChazizSquareBracketInstance(): bool
    {
        return  $this->is_chaziz_squarebracket_instance;
    }

    /**
     * function isSiteTestInstance
     *
     * Returns a bool that indicates if the instance is set to "SiteTest" mode.
     *
     * @return bool
     */
    public function isSiteTestInstance(): bool
    {
        return  $this->is_sitetest_instance;
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
     * function returnCaptchaSettings
     *
     * Returns array for the captcha settings.
     *
     * @return array
     */
    public function returnCaptchaSettings(): array
    {
        return $this->captcha_settings;
    }
}
