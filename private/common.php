<?php

namespace OpenSB;

if (version_compare(PHP_VERSION, '8.2.0') <= 0) {
    die('OpenSB is not compatible with your PHP version. OpenSB supports PHP 8.2 or newer.');
}

if (!file_exists(SB_VENDOR_PATH . '/autoload.php')) {
    die('The required Composer packages are missing. Please read the setup instructions in the README file.');
}

if (!file_exists(SB_PRIVATE_PATH . '/config/config.php')) {
    die('The configuration file could not be found. Please read the setup instructions in the README file.');
}

$config = include_once(SB_PRIVATE_PATH . '/config/config.php');

require_once(SB_VENDOR_PATH . '/autoload.php');

use SquareBracket\ErrorTemplating;
use SquareBracket\Localization;
use SquareBracket\SquareBracket;
use SquareBracket\Templating;
use SquareBracket\Utilities;
use SquareBracket\VersionNumber;

// please use apache/nginx for production stuff.
define('SB_PHP_BUILTINSERVER', php_sapi_name() === 'cli-server');
define('SB_CLI', php_sapi_name() === 'cli');

if (!SB_CLI) {
    $blacklisted_user_agents = [
        '/python-requests/i',
        '/curl/i',
    ];

    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    foreach ($blacklisted_user_agents as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            http_response_code(403);
            exit;
        }
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_name("sb_session");

        $is_secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            //'domain' => $_SERVER['HTTP_HOST'],
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

set_exception_handler(function ($exception) {
    // kinda ugly imo
    $version_number = new VersionNumber();

    if (SB_CLI) {
        $errorMsg = sprintf(
            "Error: %s" . PHP_EOL .
                "Code: %s" . PHP_EOL .
                "File: %s" . PHP_EOL .
                "Line: %s" . PHP_EOL .
                "Version: %s" . PHP_EOL .
                "Stack Trace:" . PHP_EOL . "%s" . PHP_EOL,
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $version_number->getVersionString(),
            $exception->getTraceAsString()
        );

        echo "An error has occurred:" . PHP_EOL;
        echo $errorMsg;
        die(1);
    } else {
        $errorMsg = sprintf(
            '<b>Error:</b> %s<br>'
                . '<b>Code:</b> %s<br>'
                . '<b>File:</b> %s<br>'
                . '<b>Line:</b> %s<br>'
                . '<b>Version:</b> %s<br>'
                . '<b>Stack Trace:</b><pre>%s</pre>',
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $version_number->getVersionString(),
            $exception->getTraceAsString()
        );

        echo sprintf(
            "<h1>An error has occurred</h1>" .
                "<div style='padding: 1em; border: 1px solid red;'>" .
                "<pre>%s</pre>" .
                "</div>",
            $errorMsg,
        );
        die();
    }
});

// now initialize the orange classes
$orange = new SquareBracket($config);
$database = $orange->getDatabaseClass();

if (!SB_CLI) {
    $auth = $orange->getAuthenticationClass(); // temporary ig?

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

    $ipban = $database->fetch(
        "SELECT * FROM ip_bans WHERE ? LIKE ip OR ? LIKE ip",
        [Utilities::getIpAddress(), Utilities::getIpAddress(false)]
    );

    if ($ipban) {
        $usersAssociatedWithIP = $database->fetchArray($database->query(
            "SELECT name FROM users WHERE ip LIKE ? OR ip LIKE ?",
            [Utilities::getIpAddress(), Utilities::getIpAddress(false)]
        ));

        if ($orange->isDebug() && (!$ipban)) {
            $ipban = [
                "ip" => Utilities::getIpAddress(false),
            ];
        }

        http_response_code(403);
        echo $twig_error->render("ip_banned.twig", [
            "page" => "ip-banned",
            "data" => $ipban,
            "users" => $usersAssociatedWithIP,
        ]);
        die();
    }

    if ($orange->isUnderMaintenance() && !SB_PHP_BUILTINSERVER) {
        http_response_code(503);
        echo $twig_error->render("offline.twig", ["page" => "failwhale"]);
        die();
    }

    $twig = new Templating($orange);
}
