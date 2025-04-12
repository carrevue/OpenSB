<?php
namespace SquareBracket;

/**
 * The core OpenSB class.
 */
class SquareBracket {
    private Database $database;
    public array $options;
    private array $accounts;
    private string $accounts_cookie_warning = "DO-NOT-SHARE-THIS-WITH-ANYONE-";

    /**
     * Initialize core OpenSB classes. (this is fucking stupid)
     *
     */
    public function __construct($config) {
        global $isChazizSB;

        // extract mysql settings
        $host = $config["mysql"]["host"];
        $db = $config["mysql"]["database"];
        $user = $config["mysql"]["username"];
        $pass = $config["mysql"]["password"];

        $isDebug = ($config["mode"] ?? '') === "DEV";

        if (isset($_COOKIE["SBOPTIONS"])) {
            $this->options = json_decode(base64_decode($_COOKIE["SBOPTIONS"]), true);
        } else {
            $defaultSkin = "biscuit";
            if ($isChazizSB) {
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

        try {
            $this->database = new Database($host, $user, $pass, $db);
            // enable db profiler (not to be confused with the other profiler)
            // if we are on debug mode
            if ($isDebug) {
                $this->database->setProfiling(true);
            }
        } catch (CoreException $e) {
            $e->page();
        }
    }

    /**
     * Returns the database class for other OpenSB classes to use.
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
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
}