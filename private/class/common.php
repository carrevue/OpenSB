<?php

namespace OpenSB;

if (version_compare(PHP_VERSION, '8.2.0') <= 0) {
    die('<strong>OpenSB is not compatible with your PHP version. OpenSB supports PHP 8.2 or newer.</strong>');
}

if (!file_exists(SB_VENDOR_PATH . '/autoload.php')) {
    die('<strong>You are missing the required Composer packages. Please read the installing instructions in the README file.</strong>');
}

// yes. you can call me stupid for this. but this is done because i don't want the new code to use the old shitty
// configs. -chaziz 7/31/2024
if (!file_exists(SB_PRIVATE_PATH . '/config/config.php')) {
    die('<strong>The configuration file could not be found. Please read the installing instructions in the README file.</strong>');
}

$config = include_once(SB_PRIVATE_PATH . '/config/config.php');

require_once(SB_VENDOR_PATH . '/autoload.php');

use SquareBracket\Authentication;
use SquareBracket\ErrorTemplating;
use SquareBracket\Localization;
use SquareBracket\Profiler;
use SquareBracket\SquareBracket;
use SquareBracket\Storage;
use SquareBracket\Templating;
use SquareBracket\Utilities;

// please use apache/nginx for production stuff.
if (php_sapi_name() == "cli-server") {
    define("SB_PHP_BUILTINSERVER", true);
} else {
    define("SB_PHP_BUILTINSERVER", false);
}

if (php_sapi_name() == "cli") {
    define("SB_CLI", true);
} else {
    define("SB_CLI", false);
}

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

// since opensb orange is shitty code and uses global everywhere. convert new config variables
// to old global config variables to avoid fucking around with the legacy orange code.
$host = $config["mysql"]["host"];
$db = $config["mysql"]["database"];
$user = $config["mysql"]["username"];
$pass = $config["mysql"]["password"];

$captcha = $config["captcha"];

if ($config["site"] == "squarebracket_chaziz") {
    $isChazizSB = true;
} else {
    $isChazizSB = false;
    if ($config["site"] != "squarebracket") {
        trigger_error("This variable should be set to either squarebracket or squarebracket_chaziz.",
            E_USER_ERROR);
    }
}

if ($config["mode"] == "DEV") {
    $isDebug = true;
} else {
    $isDebug = false;
}

if ($config["cache"]) {
    $enableCache = true;
} else {
    $enableCache = false;
}

if ($config["maintenance"]) {
    $isMaintenance = true;
} else {
    $isMaintenance = false;
}

if ($config["invite_keys"]) {
    $enableInviteKeys = true;
} else {
    $enableInviteKeys = false;
}

// Branding settings
$branding = [
    "name" => $config["branding"]["name"],
    "assets_location" => $config["branding"]["assets"],
];

// TODO: port these into settings that can be changed in-site via the admin panel.
$disableRegistration = false;
$disableUploading = false;
$disableWritingJournals = false;

// now initialize the orange classes
$orange = new SquareBracket($host, $user, $pass, $db);
$database = $orange->getDatabase();

$localization_setting = $orange->getLocalOptions()["locale"] ?? "en-US";

$storage = new Storage($orange->getDatabase());

if (!SB_CLI) {
    $auth = new Authentication($database);
    $profiler = new Profiler();
    $localization = new Localization($localization_setting);

    // automatic stuff
    // this should probably have a cooldown or something i don't fucking know

    // automatically ban accounts linked to banned ips.
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

    $twig = new Templating($orange);
    $twig_error = new ErrorTemplating($orange);

    if ($ipban = $database->fetch("SELECT * FROM ip_bans WHERE ? LIKE ip", [Utilities::getIpAddress()])) {
        $usersAssociatedWithIP = $database->fetchArray($database->query("SELECT name FROM users WHERE ip LIKE ?", [Utilities::getIpAddress()]));

        echo $twig_error->render("ip_banned.twig", [
            "page" => "ip-banned",
            "data" => $ipban,
            "users" => $usersAssociatedWithIP,
        ]);
        die();
    }

    if ($isMaintenance && !SB_PHP_BUILTINSERVER) {
        echo $twig_error->render("offline.twig", ["page" => "failwhale"]);
        die();
    }
}