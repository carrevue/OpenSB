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

use BluffingoCore\Database;
use BluffingoCore\Profiler;

/**
 * The core OpenSB class.
 */
class SquareBracket
{
    private Database $database;
    private Profiler $profiler;
    private Storage $storage;
    private Authentication $authentication;
    private Localization $localization;
    private ?DiscordWebhookLogging $discord;
    private ?IPLookup $ip_lookup;
    private bool $is_debug = false;
    private bool $is_chaziz_squarebracket_instance = false;
    private bool $is_sitetest_instance = false;
    private bool $template_caching_enabled = false;
    private bool $under_maintenance = false;
    private bool $enable_account_registration = true;
    private bool $enable_invite_keys = false;
    private bool $enable_lockdown = false;
    private bool $enable_discord_webhook = false;
    private bool $enable_ip_lookup = false;
    private array $branding_settings;
    private array $captcha_settings;
    public array $options;
    private array $accounts;
    private string $accounts_cookie_warning = "DO-NOT-SHARE-THIS-WITH-ANYONE-";

    /**
     * Initialize core OpenSB classes. (this is fucking stupid)
     *
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
        $this->authentication = new Authentication($this);

        // super dangerous if misused, but that site mode is only intended for
        // squarebracket production so it doesnt really matter. -chaziz 09/17/2025
        if (
            $this->is_chaziz_squarebracket_instance &&
            isset($this->authentication->getUserData()["name"]) &&
            isset($this->authentication->getUserData()["id"]) &&
            $this->authentication->getUserData()["name"] == "Chaziz" &&
            $this->authentication->getUserData()["id"] == 1
        ) {
            $this->is_debug = true;
            // this doesnt show auth-related queries however, but it doesnt really matter.
            $this->database->setProfiling(true);
        }

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

        $this->localization = new Localization($this->options["locale"] ?? "en-US");

        if (isset($_COOKIE["SBACCOUNTS"])) {
            $accounts_cookie_without_warning = str_replace($this->accounts_cookie_warning, "", $_COOKIE["SBACCOUNTS"]);
            $this->accounts = json_decode(base64_decode($accounts_cookie_without_warning), true);
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

    private function overrideBrandingWithFulpTube()
    {
        $this->branding_settings = [
            "name" => "FulpTube",
            "assets_location" => "/assets/sb_branding/fulp",
            "is_vector" => true,
            "use_wordmark" => true,
        ];
    }

    public function getOptionsCookie()
    {
        if (isset($_COOKIE['SBOPTIONS'])) {
            return json_decode(base64_decode($_COOKIE['SBOPTIONS']), true);
        } else {
            return [];
        }
    }

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
     * Returns the database class for other classes to use.
     *
     * @return Database
     */
    public function getDatabaseClass(): Database
    {
        return $this->database;
    }

    /**
     * Returns the profiler class for other OpenSB classes to use.
     *
     * @return Profiler
     */
    public function getProfilerClass(): Profiler
    {
        return $this->profiler;
    }

    /**
     * Returns the storage class for other OpenSB classes to use.
     *
     * @return Storage
     */
    public function getStorageClass(): Storage
    {
        return $this->storage;
    }

    /**
     * Returns the authentication class for other OpenSB classes to use.
     *
     * @return Authentication
     */
    public function getAuthenticationClass(): Authentication
    {
        return $this->authentication;
    }

    /**
     * Returns the localization class for other OpenSB classes to use.
     *
     * @return Localization
     */
    public function getLocalizationClass(): Localization
    {
        return $this->localization;
    }

    /**
     * Returns the bool that toggles the Discord webhook logging class.
     *
     * @return bool
     */
    public function isDiscordWebhookEnabled(): bool
    {
        return $this->enable_discord_webhook;
    }

    /**
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
     * Returns the bool that toggles the IP lookup class.
     *
     * @return bool
     */
    public function isIpLookupEnabled(): bool
    {
        return $this->enable_ip_lookup;
    }

    /**
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
     * Returns the user's local settings.
     *
     * @return array
     */
    public function getLocalOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns warning string for accounts cookie.
     *
     * @return string
     */
    public function getWarningString(): string
    {
        return $this->accounts_cookie_warning;
    }

    /**
     * Returns array for changing accounts.
     *
     * @return array|string
     */
    public function getAccountsArray(): array|string
    {
        return $this->accounts;
    }

    /**
     * Returns boolean that indicates if debug is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->is_debug;
    }

    /**
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
     * Returns boolean for enabling template caching.
     *
     * @return bool
     */
    public function isTemplateCachingEnabled(): bool
    {
        return $this->template_caching_enabled;
    }

    /**
     * Returns boolean for enabling account registration.
     *
     * @return bool
     */
    public function isAccountRegistrationEnabled(): bool
    {
        return $this->enable_account_registration;
    }

    /**
     * Returns boolean for enabling invite keys for account registration.
     *
     * @return bool
     */
    public function isInviteKeysEnabled(): bool
    {
        return $this->enable_invite_keys;
    }

    /**
     * Returns boolean for enabling lockdown.
     *
     * @return bool
     */
    public function isLockdownEnabled(): bool
    {
        return $this->enable_lockdown;
    }

    /**
     * Returns boolean for if the instance is under maintenance.
     *
     * @return bool
     */
    public function isUnderMaintenance(): bool
    {
        return $this->under_maintenance;
    }

    /**
     * Returns a bool that indicates if the instance is set to "Chaziz SquareBracket" mode.
     *
     * @return bool
     */
    public function isChazizSquareBracketInstance(): bool
    {
        return  $this->is_chaziz_squarebracket_instance;
    }

    /**
     * Returns a bool that indicates if the instance is set to "SiteTest" mode.
     *
     * @return bool
     */
    public function isSiteTestInstance(): bool
    {
        return  $this->is_sitetest_instance;
    }

    /**
     * Returns array for the instance's branding.
     *
     * @return array
     */
    public function getBrandingSettings(): array
    {
        return $this->branding_settings;
    }

    /**
     * Returns array for the captcha settings.
     *
     * @return array
     */
    public function returnCaptchaSettings(): array
    {
        return $this->captcha_settings;
    }
}
