<?php

namespace OpenSB;

if (version_compare(PHP_VERSION, '8.2.0') <= 0) {
    die('OpenSB is not compatible with your PHP version. OpenSB supports PHP 8.2 or newer.');
}

if (!file_exists(SB_VENDOR_PATH . '/autoload.php')) {
    die('The required Composer packages are missing. Please read the setup instructions in the README file.');
}

// yes. you can call me stupid for this. but this is done because i don't want the new code to use the old shitty
// configs. -chaziz 7/31/2024
if (!file_exists(SB_PRIVATE_PATH . '/config/config.php')) {
    die('The configuration file could not be found. Please read the setup instructions in the README file.');
}

$config = include_once(SB_PRIVATE_PATH . '/config/config.php');

$isDebug = ($config["mode"] ?? '') === "DEV";

require_once(SB_VENDOR_PATH . '/autoload.php');

use SquareBracket\Authentication;
use SquareBracket\CoreException;
use SquareBracket\ErrorTemplating;
use SquareBracket\Localization;
use SquareBracket\Profiler;
use SquareBracket\SquareBracket;
use SquareBracket\Storage;
use SquareBracket\Templating;
use SquareBracket\Utilities;

// please use apache/nginx for production stuff.
define('SB_PHP_BUILTINSERVER', php_sapi_name() === 'cli-server');
define('SB_CLI', php_sapi_name() === 'cli');

if (!SB_CLI) {
    if (session_status() === PHP_SESSION_NONE) {
        session_name("sb_session");

        $is_secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start([
            "cookie_lifetime" => 1209600,
            "gc_maxlifetime" => 1209600,
        ]);
    }
}

spl_autoload_register(function ($class_name) {
    $class_name = str_replace('\\', '/', $class_name);
    if (file_exists(SB_PRIVATE_PATH . "/class/$class_name.php")) {
        require SB_PRIVATE_PATH . "/class/$class_name.php";
    }
});

// FIXME: what the fuck is this piece of shit -chaziz 4/9/2025
// WIP: moving these to the core "SquareBracket" class. -chaziz 4/12/2025
$captcha = $config["captcha"];

$allowedSites = ['squarebracket', 'squarebracket_chaziz'];
if (!in_array($config["site"], $allowedSites)) {
    die("The site variable in the configuration file should be either set to squarebracket or squarebracket_chaziz.");
}
$isChazizSB = ($config["site"] === "squarebracket_chaziz");

$enableCache = (bool)($config["cache"] ?? false);
$isMaintenance = (bool)($config["maintenance"] ?? false);
$enableInviteKeys = (bool)($config["invite_keys"] ?? false);

// TODO: port these into settings that can be changed through the admin panel
$disableRegistration = !($config["enable_registration"] ?? false);

$lockdown = (bool)($config["lockdown"] ?? false);
$disableUploading = $lockdown;
$disableWritingJournals = $lockdown;

try {
// now initialize the orange classes
    $orange = new SquareBracket($config);
    $database = $orange->getDatabase();

    $profiler = new Profiler($database, $isDebug);

    $localization_setting = $orange->getLocalOptions()["locale"] ?? "en-US";

    $storage = new Storage($orange->getDatabase());

    if (!SB_CLI) {
        $auth = new Authentication($database);
        $localization = new Localization($localization_setting);

        // automatic stuff
        // this should probably have a cooldown or something i don't fucking know

        // automatically ban accounts linked to banned ips.
        // TODO: add ip ban functionality in admin panel instead of this crude ass shit
        /*
        $ipBannedUsers = $database->fetchArray($database->query("SELECT * from ip_bans"));
        foreach ($ipBannedUsers as $ipBannedUser) {
            $usersAssociatedWithIP = $database->fetchArray($database->query("SELECT id, name FROM users WHERE ip LIKE ?", [$ipBannedUser["ip"]]));
            foreach ($usersAssociatedWithIP as $ipBannedUser2) { // i can't really name variables that well
                if (!$database->fetch("SELECT b.userid FROM user_bans b WHERE b.userid = ?", [$ipBannedUser2["id"]])) {
                    $database->query("INSERT INTO user_bans (userid, reason, time) VALUES (?,?,?)",
                        [$ipBannedUser2["id"], "Automated by OpenSB", time()]);
                }
            }
        }
        */

        $twig_error = new ErrorTemplating($orange);

        $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [Utilities::getIpAddress()]);

        // if theres no ipban, check again with the unencrypted ip address.
        // this is temporary.
        if (!$ipban) {
            $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [Utilities::getIpAddress(false)]);
        }

        if ($ipban) {
            $usersAssociatedWithIP = $database->fetchArray($database->query(
                "SELECT name FROM users WHERE ip LIKE ? OR ip LIKE ?",
                [Utilities::getIpAddress(), Utilities::getIpAddress(false)]));

            http_response_code(403);
            echo $twig_error->render("ip_banned.twig", [
                "page" => "ip-banned",
                "data" => $ipban,
                "users" => $usersAssociatedWithIP,
            ]);
            die();
        }

        if ($isMaintenance && !SB_PHP_BUILTINSERVER) {
            http_response_code(503);
            echo $twig_error->render("offline.twig", ["page" => "failwhale"]);
            die();
        }

        $twig = new Templating($orange);
    }
} catch (CoreException $e) {
    $e->page();
}