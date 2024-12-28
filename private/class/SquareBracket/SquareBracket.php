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
    public function __construct($host, $user, $pass, $db) {
        global $isChazizSB;

        if (isset($_COOKIE["SBOPTIONS"])) {
            $this->options = json_decode(base64_decode($_COOKIE["SBOPTIONS"]), true);
        } else {
            $defaultSkin = "biscuit";
            if ($isChazizSB) {
                $defaultSkin = "charla";
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
        } catch (CoreException $e) {
            $e->page();
        }
    }

    /**
     * Returns the database class for other OpenSB classes to use. (this is stupid design)
     *
     * @return Database
     *
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
     * @return string
     */
    public function getAccountsArray(): array|string
    {
        return $this->accounts;
    }
}