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
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

use Core\Utilities;
use Core\Router;

use Data\Account\AccountFlags;
use Data\User\UserRoleEnum;
use Data\User\UserFlags;

require_once SB_PRIVATE_PATH . '/common.php';

global $sb;

function load_thumbnail_from_skin($path): never
{
    $pathParts = explode('_', $path);
    $skin = $pathParts[0] ?? '';
    $theme = $pathParts[1] ?? 'default.png';

    $skinPath = SB_PRIVATE_PATH . '/skins/' . $skin . '/' . $theme;

    if (file_exists($skinPath)) {
        load_file($skinPath, "image/png");
        exit;
    } else {
        Utilities::redirect('/assets/unknown_theme.png');
    }
}

// /private/skins/{skin}/assets/{path}
function load_asset_from_skin(string $skin, string $path): never
{
    $skinAssetDirectory = SB_PRIVATE_PATH . "/skins/{$skin}/assets";
    $fullPath = realpath($skinAssetDirectory . '/' . $path);

    // validation
    if (
        $fullPath === false ||
        !str_starts_with($fullPath, $skinAssetDirectory) ||
        !is_file($fullPath)
    ) {
        http_response_code(404);
        die();
    }

    // get mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $fullPath) ?: 'application/octet-stream';

    load_file($fullPath, $mime);
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
        $ipban = $database->fetch("SELECT * FROM ip_bans WHERE ip = ?", [Utilities::getIpAddress()]);

        if (!$ipban) {
            $database->query("INSERT INTO ip_bans (ip, reason, timestamp) VALUES (?, ?, ?)", [
                $ip,
                "Automated by OpenSB: Likely a bot.",
                time()
            ]);
            http_response_code(403);
            die();
        }
    }
}

if ($sb->isTestInstance())
{
    $uri = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
    $path = explode('/', $uri);

    $auth = $sb->getAuthenticationClass();

    // if the user is still logged in but isnt staff (or doesnt have QA access flag), log them out.
    if (
        $auth->isLoggedIn()
        && !$auth->userHasRole(UserRoleEnum::Moderator)
        && !($auth->getAccountFlags() & AccountFlags::FLAG_QA_ACCESS->value)
    ) {
        $auth->logOut();
    }

    if (Utilities::getIpAddress() != "localhost" && !$auth->isLoggedIn() && $path[1] != "login") {
        Utilities::redirect("/login");
    }
}

$router = new Router();

// homepage
$router->add('/', 'index.php');
$router->add('/index', 'index.php');

// standard pages
$router->add('/about', 'about.php');
$router->add('/browse', 'browse.php');
$router->add('/collection/{id}', 'collection.php');
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
$router->add('/members', 'members.php');
$router->add('/my_account', 'my_account.php');
$router->add('/my_invite_keys', 'my_invite_keys.php');
$router->add('/my_messages', 'my_messages.php');
$router->add('/my_profile', 'my_profile.php');
$router->add('/my_uploads', 'my_uploads.php');
$router->add('/news', 'news.php');
$router->add('/notifications', 'notifications.php');
$router->add('/privacy', 'privacy.php');
$router->add('/playlist', 'playlist.php');
$router->add('/playlist/{id}', 'playlist.php');
//$router->add('/read', 'read.php'); TODO
//$router->add('/read/{id}', 'read.php'); TODO
$router->add('/search', 'search.php');
$router->add('/settings', 'settings.php');
$router->redirect('/staff', '/about');
$router->add('/theme', 'theme.php');
$router->add('/tos', 'tos.php');
$router->add('/upload', 'upload.php');
$router->redirect('/users', '/members');
$router->add('/verify_birthdate', 'verify_birthdate.php');
$router->add('/verify_email', 'verify_email.php');
$router->redirect('/version', '/about');
$router->add('/write', 'write.php');
$router->add('/view/{id}', 'view.php');

if ($sb->isIncompleteFeaturesEnabled()) {
    $router->add('/experiment_flags', 'experiment_flags.php');
}

if (Utilities::isClassicSkin()) {
    $router->add('/watch', 'view.php');
} else {
    $router->add('/watch', function () {
        if (isset($_GET['v'])) Utilities::redirect('/view/' . $_GET['v'], 301);
    });
}

// user profiles
$router->add('/channel', function () {
    if (isset($_GET['n'])) Utilities::redirect('/user/' . $_GET['n'], 301); // og fulptube
});

$router->add('/user', function () {
    if (isset($_GET['name'])) Utilities::redirect('/user/' . $_GET['name'], 301); // old sb
    if (isset($_GET['n'])) Utilities::redirect('/user/' . $_GET['n'], 301); // og fulptube
});
$router->add('/user/{username}', 'profile/overview.php'); // overview
$router->add('/user/{username}/uploads', 'profile/uploads.php'); // uploads
$router->add('/user/{username}/comments', 'profile/comments.php'); // comments
$router->add('/user/{username}/journals', 'profile/journals.php'); // journals
$router->add('/user/{username}/about', 'profile/about.php'); // about (mainly finalium-specific)

$router->add('/user/{username}/journal/{id}', 'profile/journal.php');

// api
$router->add('/api/skin/comment_load', 'api/skin/comment_load.php'); // finalium-only
$router->add('/api/skin/comment_send', 'api/skin/comment_send.php'); // trinium-only
$router->add('/api/skin/upload_interaction', 'api/skin/upload_interaction.php');
$router->add('/api/skin/user_interaction', 'api/skin/user_interaction.php'); // trinium-only
$router->add('/api/skin/validate_username', 'api/skin/validate_username.php');

// only used by bootstrap and finalium (old, trash and deprecated)
$router->add('/api/legacy/ajax_watch', (function () {
    // the old finalium ajax_watch implementation was fucked beyond repair
    // so i'll wait until later on to reimplement this -chaziz 08/29/2025
    die("This page intentionally left blank.");
}));
$router->add('/api/legacy/comment', 'api/legacy/comment.php');
$router->add('/api/legacy/rate', 'api/legacy/rate.php');
$router->add('/api/legacy/subscribe', 'api/legacy/subscribe.php');

// data api (not fully complete and probably won't be for a while)
$router->add('/api/data/get_comments', 'api/data/get_comments.php');
$router->add('/api/data/get_instance_info', 'api/data/get_instance_info.php');
$router->add('/api/data/get_upload', 'api/data/get_upload.php');
$router->add('/api/data/get_uploads', 'api/data/get_uploads.php');

// redirect to dashboard
$router->redirect('/admin', '/dashboard');
$router->redirect('/admin/{page}', '/dashboard'); // just redirect to /dashboard for now

// dashboard routes
$router->add('/dashboard', 'dashboard/index.php');
$router->add('/dashboard/login', 'dashboard/login.php');
$router->add('/dashboard/asn_bans', 'dashboard/asn_bans.php');
$router->add('/dashboard/users', 'dashboard/users.php');
$router->add('/dashboard/users/{username}', 'dashboard/user_edit.php');
$router->redirect('/dashboard/overview', '/dashboard');
$router->add('/dashboard/uploads', 'dashboard/uploads.php');
$router->add('/dashboard/uploads/{id}', 'dashboard/upload_edit.php');
$router->add('/dashboard/posts', 'dashboard/posts.php');
$router->add('/dashboard/invite_keys', 'dashboard/invite_keys.php');
$router->add('/dashboard/ip_bans', 'dashboard/ip_bans.php');
$router->add('/dashboard/filtering', 'dashboard/filtering.php');
$router->add('/dashboard/statistics', 'dashboard/statistics.php');
$router->add('/dashboard/server', 'dashboard/server.php');

// trinium icons (used by trinium)
$router->add('/assets/icons.svg', function () {
    load_file(SB_PRIVATE_PATH . '/icons/sprite.svg', 'image/svg+xml');
});

// bootstrap icons (used by bootstrap)
$router->add('/assets/bootstrap-icons.svg', function () {
    load_file(SB_VENDOR_PATH . '/twbs/bootstrap-icons/bootstrap-icons.svg', 'image/svg+xml');
});

// bootstrap js (used by bootstrap)
$router->add('/assets/bootstrap.js', function () {
    load_file(SB_VENDOR_PATH . '/twbs/bootstrap/dist/js/bootstrap.bundle.min.js', 'application/javascript');
});

// skin assets (ONLY WORKS IF THE ASSET IS NOT IN A NESTED FOLDER)
$router->add('/assets/skin/{skin}/{asset}', function (array $params) {
    load_asset_from_skin($params['skin'], $params['asset']);
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
    '/wp-includes/{path}',
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
