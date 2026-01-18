<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2023-2026 Chaziz

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

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_DYNAMIC_PATH", SB_ROOT_PATH . '/dynamic');
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

use OpenSB\Utilities;
use OpenSB\Router;

require_once SB_PRIVATE_PATH . '/common.php';

// TODO: make this cachable
function load_thumbnail_from_skin($path): never
{
    $pathParts = explode('_', $path);
    $skin = $pathParts[0] ?? '';
    $theme = $pathParts[1] ?? 'default.png';

    $skinPath = SB_PRIVATE_PATH . '/skins/' . $skin . '/' . $theme;

    if (file_exists($skinPath)) {
        header('Content-Type: image/png');
        readfile($skinPath);
        exit;
    } else {
        Utilities::redirect('/assets/unknown_theme.png');
    }
}


// this is very ugly, i know.
function load_file(string $path, string $content_type): never
{
    if (!file_exists($path)) {
        http_response_code(404);
        exit;
    }

    $last_modified = filemtime($path);
    $etag = md5_file($path);

    // caching shit
    header("Last-Modified: " . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
    header("Etag: $etag");

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
        $if_modified_since = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
        $if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

        if (($if_modified_since && $if_modified_since >= $last_modified) ||
            ($if_none_match && $if_none_match === $etag)
        ) {
            http_response_code(304);
            exit;
        }
    }

    header("Content-Type: $content_type");
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=43200'); // 12 hours
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 43200) . ' GMT');

    readfile($path);
    exit;
}

function last_resort(): void
{
    global $twig_error; // Ugly

    if (str_contains($_SERVER["REQUEST_URI"], '.php')) {
        $newUrl = str_replace('.php', '', $_SERVER["REQUEST_URI"]);
        header('Location: ' . $newUrl, true, 301);
        die();
    }

    http_response_code(404);
    echo $twig_error->render("404.twig", ["page" => "failwhale"]);
}

function handle_debug_page_path(string $path): void
{
    $debug_pages_path = SB_PRIVATE_PATH . '/pages/debug/';

    if (!$path) $path = "index";
    $path = str_replace(['..', '/', '\\'], '', $path);

    $full_path = $debug_pages_path . $path . ".php";

    if (file_exists($full_path) && str_starts_with(realpath($full_path), $debug_pages_path)) {
        require $full_path;
    } else {
        last_resort();
    }
}

function automatic_ip_ban()
{
    global $database;

    $ip = Utilities::getIpAddress();
    if ($ip !== null) {
        $database->query("INSERT INTO ip_bans (ip, reason, timestamp) VALUES (?, ?, ?)", [
            $ip,
            "Automated by OpenSB: Likely a bot.",
            time()
        ]);
        http_response_code(403);
        die();
    }
}

$router = new Router();

// homepage
$router->add('/', 'index.php');
$router->add('/index', 'index.php');

// standard pages
$router->add('/browse', 'browse.php');
$router->add('/login', 'login.php');
$router->add('/login/{user}', 'login.php');
$router->add('/register', 'register.php');
$router->add('/edit', 'edit.php');
$router->add('/feature', 'feature.php');
$router->add('/delete', 'delete.php');
$router->add('/design_test', 'design_test.php');
$router->add('/guidelines', 'guidelines.php');
$router->add('/help', 'help.php');
$router->add('/journals', 'journals.php');
$router->add('/journals/{user}', 'journals.php');
$router->add('/license', 'license.php');
$router->add('/logout', 'logout.php');
$router->add('/my_invite_keys', 'my_invite_keys.php');
$router->add('/my_messages', 'my_messages.php');
$router->add('/my_uploads', 'my_uploads.php');
$router->add('/notifications', 'notifications.php');
$router->add('/privacy', 'privacy.php');
$router->add('/read', 'read.php');
$router->add('/read/{id}', 'read.php');
$router->add('/search', 'search.php');
$router->add('/settings', 'settings.php');
$router->add('/staff', 'staff.php');
$router->add('/theme', 'theme.php');
$router->add('/tos', 'tos.php');
$router->add('/upload', 'upload.php');
$router->add('/users', 'users.php');
$router->add('/verify_birthdate', 'verify_birthdate.php');
$router->add('/version', 'version.php');
$router->add('/watch', function () {
    if (isset($_GET['v'])) Utilities::redirect('/view/' . $_GET['v'], 301);
});
$router->add('/view/{id}', 'view.php');
$router->add('/write', 'write.php');

// user profiles
$router->add('/user', function () {
    if (isset($_GET['name'])) Utilities::redirect('/user/' . $_GET['name'], 301);
    if (isset($_GET['n'])) Utilities::redirect('/user/' . $_GET['n'], 301);
});
$router->add('/user/{username}', 'profile_overview.php'); // overview
$router->add('/user/{username}/uploads', 'profile_uploads.php'); // uploads
$router->add('/user/{username}/comments', 'profile_comments.php'); // comments
$router->add('/user/{username}/journals', 'profile_journals.php'); // journals
$router->add('/user/{username}/about', 'profile_about.php'); // about (mainly fulphiker-specific)

// api
$router->add('/api/frontend/comment_load', 'api/frontend/comment_load.php'); // finalium-only
$router->add('/api/frontend/comment_send', 'api/frontend/comment_send.php'); // trinium-only
$router->add('/api/frontend/upload_interaction', 'api/frontend/upload_interaction.php'); // trinium-only
$router->add('/api/frontend/user_interaction', 'api/frontend/user_interaction.php'); // trinium-only

// only used by bootstrap and finalium (old, trash and deprecated)
$router->add('/api/legacy/ajax_watch', (function () {
    // the old finalium ajax_watch implementation was fucked beyond repair
    // so i'll wait until later on to reimplement this -chaziz 08/29/2025
    die("This page intentionally left blank.");
}));
$router->add('/api/legacy/comment', 'api/legacy/comment.php');
$router->add('/api/legacy/rate', 'api/legacy/rate.php');
$router->add('/api/legacy/subscribe', 'api/legacy/subscribe.php');

// json api (not fully complete and probably won't be for a while)
// this is disabled on sb/fulp prod (see https://github.com/bluffingo/OpenSB/issues/353)
if (!$sb->isChazizSquareBracketInstance()) {
    $router->add('/api/v3/get_comments', 'api/v3/get_comments.php');
    $router->add('/api/v3/get_instance_info', 'api/v3/get_instance_info.php');
    $router->add('/api/v3/get_upload', 'api/v3/get_upload.php');
    $router->add('/api/v3/get_uploads', 'api/v3/get_uploads.php');
}

// redirect to dashboard
$router->redirect('/admin', '/dashboard');
$router->redirect('/admin/{page}', '/dashboard'); // just redirect to /dashboard for now

// dashboard routes
$router->add('/dashboard/login', 'dashboard/login.php');
$router->add('/dashboard/users', 'dashboard/users.php');
$router->add('/dashboard/users/{username}', 'dashboard/user_edit.php');
$router->add('/dashboard/overview', 'dashboard/overview.php');
$router->add('/dashboard/uploads', 'dashboard/uploads.php');
$router->add('/dashboard/uploads/{id}', 'dashboard/upload_edit.php');
$router->add('/dashboard/interactions', 'dashboard/interactions.php');
$router->add('/dashboard/invite_keys', 'dashboard/invite_keys.php');
$router->add('/dashboard/ip_bans', 'dashboard/ip_bans.php');
$router->add('/dashboard/server', 'dashboard/server.php');
$router->redirect('/dashboard', '/dashboard/overview', 301);

// trinium icons (used by trinium)
$router->add('/assets/icons.svg', function () {
    load_file(SB_PRIVATE_PATH . '/icons/sprite.svg', 'image/svg+xml');
});

// bootstrap icons (used by bootstrap and finalium)
$router->add('/assets/bootstrap-icons.svg', function () {
    load_file(SB_VENDOR_PATH . '/twbs/bootstrap-icons/bootstrap-icons.svg', 'image/svg+xml');
});

// used by the theme page for images
$router->add('/assets/previews/{image}', function (array $params) {
    load_thumbnail_from_skin($params['image']);
});

// debug shit
$router->add('/debug', function (array $params) {
    handle_debug_page_path("index");
});
$router->add('/debug/{page}', function (array $params) {
    handle_debug_page_path($params['page']);
});

// booby traps for spambots. DO NOT CHECK THESE YOURSELF. YOU WILL BE IP BANNED.
$spam_paths = [
    '/wp_login',
    '/wp-admin',
    '/wordpress',
    '/wp',
    '/wp-admin/{path}',
    '/wordpress/{path}',
    '/wp/{path}',
    '/wp-content/{path}',
    '/xmlrpc',
    '/OA_HTML/{path}',
    '/xwiki/{path}',
    '/owa',
    '/owa/{path}',
    '/cpanel',
    '/cpanel/{path}',
];

$ban = function () { // awkward as fuck but it works
    automatic_ip_ban();
};

foreach ($spam_paths as $p) {
    $router->add($p, $ban);
}

// fallback
$router->setFallback(function () {
    last_resort();
});

// and now, the moment you've been waiting for...
$router->dispatch();
