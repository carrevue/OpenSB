<?php
namespace SquareBracket;

/**
 * The core OpenSB class.
 */
class SquareBracket {
    private Database $database;
    //private VersionNumber $version_number;
    private Profiler $profiler;
    private Storage $storage;
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
    public function __construct($config) {
        // extract mysql settings
        $host = $config["mysql"]["host"];
        $db = $config["mysql"]["database"];
        $user = $config["mysql"]["username"];
        $pass = $config["mysql"]["password"];

        $this->is_debug = ($config["mode"] ?? '') === "DEV";

        $this->database = new Database($host, $user, $pass, $db);
        // enable db profiler (not to be confused with the other profiler)
        // if we are on debug mode
        if ($this->is_debug) {
            $this->database->setProfiling(true);
        }

        //$this->version_number = new VersionNumber();
        $this->profiler = new Profiler($this->database);
        $this->storage = new Storage($this);

        $this->captcha_settings = $config["captcha"];

        $allowedSites = ['squarebracket', 'squarebracket_chaziz'];
        if (!in_array($config["site"], $allowedSites)) {
            trigger_error("The site mode in the configuration file should be 
            set either to squarebracket or squarebracket_chaziz.", E_USER_WARNING);
        }
        $this->is_chaziz_squarebracket_instance = ($config["site"] === "squarebracket_chaziz");

        $this->template_caching_enabled = (bool)($config["cache"] ?? false);

        // TODO: port these into settings that can be changed through the admin panel
        $this->under_maintenance = (bool)($config["maintenance"] ?? false);
        $this->enable_account_registration = ($config["enable_registration"] ?? false);
        $this->enable_invite_keys = (bool)($config["invite_keys"] ?? false);
        $this->enable_lockdown = (bool)($config["lockdown"] ?? false);
        //

        if (isset($_COOKIE["SBOPTIONS"])) {
            $this->options = json_decode(base64_decode($_COOKIE["SBOPTIONS"]), true);
        } else {
            $defaultSkin = "biscuit";
            if ($this->is_chaziz_squarebracket_instance) {
                // if we're on fulptube, set the default frontend to finalium 1, since its close enough to
                // early-hitchhiker youtube (which is what og fulptube used to be based on). otherwise,
                // set the default to charla. -chaziz 4/7/2025

                $defaultSkin = Utilities::isFulpTube() ? "finalium" : "charla";
            }

            $this->options = [
                "skin" => $defaultSkin,
                "theme" => "default",
                "sounds" => false,
            ];
            setcookie("SBOPTIONS", base64_encode(json_encode($this->options)), 2147483647);
        }

        if (isset($_COOKIE["SBACCOUNTS"])) {
            $stupid_fucking_bullshit = str_replace($this->accounts_cookie_warning, "", $_COOKIE["SBACCOUNTS"]);
            $this->accounts = json_decode(base64_decode($stupid_fucking_bullshit), true);
        } else {
            $this->accounts = [];
        }

        // override squarebracket branding with fulptube branding if accessed via fulptube.rocks.
        // this fulptube branding is meant to look like the squarebracket branding on purpose, since
        // both squarebracket.pw and fulptube.rocks lead to the same site.
        if (Utilities::isFulpTube($this->options)) {
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

    /**
     * Returns the database class for other classes to use.
     *
     * @return Database
     */
    public function getDatabaseClass(): Database {
        return $this->database;
    }

    ///**
    // * Returns the version number class for other OpenSB classes to use.
    // *
    // * @return VersionNumber
    // */
    //public function getVersionNumberClass(): VersionNumber
    //{
    //    return $this->version_number;
    //}

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
     * Returns the user's local settings.
     *
     * @return array
     */
    public function getLocalOptions(): array {
        return $this->options;
    }

    /**
     * Returns warning string for accounts cookie.
     *
     * @return string
     */
    public function getWarningString(): string {
        return $this->accounts_cookie_warning;
    }

    /**
     * Returns array for changing accounts.
     *
     * @return array|string
     */
    public function getAccountsArray(): array|string {
        return $this->accounts;
    }

    /**
     * Returns boolean that indicates if debug is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool {
        return $this->is_debug;
    }

    /**
     * Returns boolean that indicates if incomplete features should be enabled.
     * This is separate from isDebug.
     *
     * @return bool
     */
    public function isIncompleteFeaturesEnabled(): bool {
        return $this->options["enable_incomplete_features"] ?? false;
    }

    /**
     * Returns boolean. (temporary)
     *
     * @return bool
     */
    public function isFulpTube(): bool {
        return Utilities::isFulpTube($this->options);
    }

    /**
     * Returns boolean for enabling template caching.
     *
     * @return bool
     */
    public function isTemplateCachingEnabled(): bool {
        return $this->template_caching_enabled;
    }

    /**
     * Returns boolean for enabling account registration.
     *
     * @return bool
     */
    public function isAccountRegistrationEnabled(): bool {
        return $this->enable_account_registration;
    }

    /**
     * Returns boolean for enabling invite keys for account registration.
     *
     * @return bool
     */
    public function isInviteKeysEnabled(): bool {
        return $this->enable_invite_keys;
    }

    /**
     * Returns boolean for enabling lockdown.
     *
     * @return bool
     */
    public function isLockdownEnabled(): bool {
        return $this->enable_lockdown;
    }

    /**
     * Returns boolean for if the instance is under maintenance.
     *
     * @return bool
     */
    public function isUnderMaintenance(): bool {
        return $this->under_maintenance;
    }

    /**
     * Returns a bool that indicates if the instance is set to "Chaziz SquareBracket" mode.
     *
     * @return bool
     */
    public function isChazizSquareBracketInstance(): bool {
        return  $this->is_chaziz_squarebracket_instance;
    }

    /**
     * Returns array for the instance's branding.
     *
     * @return array
     */
    public function getBrandingSettings(): array {
        return $this->branding_settings;
    }

    /**
     * Returns array for the captcha settings.
     *
     * @return array
     */
    public function returnCaptchaSettings(): array {
        return $this->captcha_settings;
    }
}