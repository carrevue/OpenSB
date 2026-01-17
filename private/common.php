<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou
  Copyright (C) 2022 shiypc
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

namespace OpenSB;

if (version_compare(PHP_VERSION, '8.2.0') <= 0) {
    die('OpenSB is not compatible with your PHP version. OpenSB requires PHP 8.2 or newer.');
}

if (!file_exists(SB_VENDOR_PATH . '/autoload.php')) {
    die('The required Composer packages are missing.');
}

if (!file_exists(SB_PRIVATE_PATH . '/config/config.php')) {
    die('The configuration file could not be found.');
}

$required_extensions = ['gd', 'intl', 'pdo_mysql', 'curl'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    die('The required PHP extensions are missing: ' . implode(', ', $missing_extensions));
}

// TODO: add check for BluffingoCore -chaziz 07/19/2025

$config = include_once(SB_PRIVATE_PATH . '/config/config.php');

require_once(SB_VENDOR_PATH . '/autoload.php');

use OpenSB\ErrorTemplating;
use OpenSB\SquareBracket;
use OpenSB\Templating;
use OpenSB\Utilities;
use OpenSB\VersionNumber;

ini_set('session.gc_maxlifetime', 86400);

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
            'lifetime' => 86400,
            'path' => '/',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start([
            "cookie_lifetime" => 86400,
            "gc_maxlifetime" => 86400,
        ]);
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

spl_autoload_register(function ($class_name) {
    $class_name = str_replace('\\', '/', $class_name);
    if (file_exists(SB_PRIVATE_PATH . "/class/$class_name.php")) {
        require SB_PRIVATE_PATH . "/class/$class_name.php";
    }
});

set_exception_handler(function ($exception) {
    $version_number = new VersionNumber(); // kinda ugly imo

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
        http_response_code(500);

        $errorMsg = sprintf(
            '<b>Error:</b> %s<br>'
                . '<b>Code:</b> %s<br>'
                . '<b>File:</b> %s<br>'
                . '<b>Line:</b> %s<br>'
                . '<b>Version:</b> %s<br>'
                . '<b>Stack Trace:</b><pre style="white-space:pre-line;">%s</pre>',
            $exception->getMessage(),
            $exception->getCode(),
            $exception->getFile(),
            $exception->getLine(),
            $version_number->getVersionString(),
            $exception->getTraceAsString()
        );

        $githubNewIssueUrl = sprintf(
            'https://github.com/bluffingo/opensb/issues/new?title=%s&labels=bug&body=%s',
            urlencode('Error: ' . $exception->getMessage()),
            urlencode(
                "**Error**: " . $exception->getMessage() . "\n\n" .
                    "**Code**: " . $exception->getCode() . "\n" .
                    "**File**: " . $exception->getFile() . "\n" .
                    "**Line**: " . $exception->getLine() . "\n" .
                    "**URL**: " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "\n" .
                    "**Version**: " . $version_number->getVersionString() . "\n\n" .
                    "**Stack Trace**:\n```\n" . $exception->getTraceAsString() . "\n```"
            )
        );

        echo sprintf(
            "<h1>An error has occurred</h1>" .
                "<div style='padding: 1em; border: 1px solid red;'>" .
                "%s" .
                "<p>Please report this error on GitHub: <a href='%s' target='_blank'>Report</a></p>" .
                "</div>",
            $errorMsg,
            $githubNewIssueUrl,
        );
        die();
    }
});

// now initialize the sb classes
$sb = new SquareBracket($config);
$database = $sb->getDatabaseClass();

if (!SB_CLI) {
    $version_number = new VersionNumber(); // kinda ugly imo
    header('X-Powered-By: OpenSB ' . $version_number->getVersionString());

    $auth = $sb->getAuthenticationClass(); // temporary ig?

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

    $twig_error = new ErrorTemplating($sb);

    $ipban = $database->fetch(
        "SELECT * FROM ip_bans WHERE ? LIKE ip",
        [Utilities::getIpAddress()]
    );

    if ($ipban) {
        $usersAssociatedWithIP = $database->fetchArray($database->query(
            "SELECT name FROM users WHERE ip LIKE ?",
            [Utilities::getIpAddress()]
        ));

        if ($sb->isDebug() && (!$ipban)) {
            $ipban = [
                "ip" => Utilities::getIpAddress(),
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

    if ($sb->isUnderMaintenance() && !SB_PHP_BUILTINSERVER) {
        http_response_code(503);
        echo $twig_error->render("offline.twig", ["page" => "failwhale"]);
        die();
    }

    if ($sb->isChazizSquareBracketInstance() && $sb->isIpLookupEnabled()) {
        $ipLookup = $sb->getIpLookupClass();
        
        if (
            $ipLookup->getCountry(Utilities::getIpAddress()) == "GB" || // online safety act
            $ipLookup->getCountry(Utilities::getIpAddress()) == "AZ"
        ) {
            http_response_code(451);
            echo $twig_error->render("geoblock.twig", ["page" => "failwhale"]);
            die();
        }
    }

    $twig = new Templating($sb);
}
