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

namespace SquareBracket;

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
    private bool $is_debug = false;
    private bool $is_chaziz_squarebracket_instance = false;
    private bool $template_caching_enabled = false;
    private bool $under_maintenance = false;
    private bool $enable_account_registration = true;
    private bool $enable_invite_keys = false;
    private bool $enable_lockdown = false;
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

        $allowedSites = ['squarebracket', 'squarebracket_chaziz'];
        if (!in_array($config["site"], $allowedSites)) {
            trigger_error("The site mode in the configuration file should be 
            set either to squarebracket or squarebracket_chaziz.", E_USER_WARNING);
        }
        $this->is_chaziz_squarebracket_instance = ($config["site"] === "squarebracket_chaziz");

        $this->is_debug = ($config["mode"] ?? '') === "DEV";

        $this->database = new Database($host, $user, $pass, $db);
        $this->authentication = new Authentication($this->database);
        // TEMPORARY. SHOULD BE REMOVED WHEN OPENSB 1.3 IS DONE
        if (
            $this->is_chaziz_squarebracket_instance &&
            isset($this->authentication->getUserData()["name"]) &&
            $this->authentication->getUserData()["name"] == "Chaziz"
        ) {
            $this->is_debug = true;
        }

        //$this->version_number = new VersionNumber();
        $this->profiler = new Profiler($this->database);
        if ($this->is_debug) {
            // enable db profiler (not to be confused with the other profiler)
            // if we are on debug mode
            $this->database->setProfiling(true);
        }

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
            $this->options = json_decode(base64_decode($_COOKIE["SBOPTIONS"]), true);

            // the charla frontend is now called trinium
            if ($this->options["skin"] == "charla") {
                $this->options["skin"] = "trinium";
                $this->setOptionCookie();
            }

            // migrate biscuit users to trinium. the frontend has been retired.
            if ($this->options["skin"] == "biscuit") {
                $this->options["skin"] = "trinium";

                if ($this->options["theme"] == "soretro") {
                    $this->options["theme"] = "default";
                }

                $this->setOptionCookie();
                Utilities::notifyBanner("The Biscuit frontend is no longer available.", null, "primary");
            }
        } else {
            $this->setOptionCookie();
        }

        $this->localization = new Localization($this->options["locale"] ?? "en-US");

        if (isset($_COOKIE["SBACCOUNTS"])) {
            $stupid_fucking_bullshit = str_replace($this->accounts_cookie_warning, "", $_COOKIE["SBACCOUNTS"]);
            $this->accounts = json_decode(base64_decode($stupid_fucking_bullshit), true);
        } else {
            $this->accounts = [];
        }

        // override squarebracket branding with fulptube branding if accessed via fulptube.rocks.
        // this fulptube branding is meant to look like the squarebracket branding on purpose, since
        // both squarebracket.pw and fulptube.rocks lead to the same site.
        if ($this->isFulpTube()) {
            //$isFulpTube = true;
            $this->branding_settings = [
                "name" => "FulpTube",
                "assets_location" => "/assets/sb_branding/fulp",
            ];
        } else {
            //$isFulpTube = false;
            $this->branding_settings = [
                "name" => $config["branding"]["name"] ?? '',
                "assets_location" => $config["branding"]["assets"] ?? '',
            ];

            // custom branding for themes. for that Extra Accuracy™.
            if ($this->is_chaziz_squarebracket_instance) {
                if ($this->options["skin"] == "finalium" && $this->options["theme"] == "hitchhiker") {
                    $this->branding_settings = [
                        "name" => "FulpTube",
                        "assets_location" => "/assets/sb_branding/fulp",
                    ];
                } elseif ($this->options["skin"] == "finalium" || $this->options["skin"] == "bootstrap") {
                    $this->branding_settings["name"] = "squareBracket";
                }
            }
        }
    }

    private function setOptionCookie()
    {
        setcookie("SBOPTIONS", base64_encode(json_encode($this->options)), [
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
     * This is separate from isDebug.
     *
     * @return bool
     */
    public function isIncompleteFeaturesEnabled(): bool
    {
        return $this->options["enable_incomplete_features"] ?? false;
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

        $isHitchhikerTheme = ($this->getLocalOptions()['skin'] ?? '') === 'finalium'
            && ($this->getLocalOptions()['theme'] ?? '') === 'hitchhiker';

        $isDebugMode = ($this->getLocalOptions()['debug_fulptube_branding'] ?? false) && $this->isDebug();

        if ($this->isChazizSquareBracketInstance() && ($isOnFulpTubeDomain || $isHitchhikerTheme || $isDebugMode)) {
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
