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

namespace OpenSB;

define("BLUFF_ROOT_PATH", dirname(__DIR__));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

use JetBrains\PhpStorm\NoReturn;
use SquareBracket\Utilities;

require_once BLUFF_PRIVATE_PATH . '/common.php';

// very fucking ugly, temporary for now. -chaziz 4/11/2025
global $isChazizSB, $auth;

// TODO: make this cachable
#[NoReturn] function load_thumbnail_from_skin($path)
{
    $pathParts = explode('_', $path);
    $skin = $pathParts[0] ?? '';
    $theme = $pathParts[1] ?? 'default.png';

    $skinPath = BLUFF_PRIVATE_PATH . '/skins/' . $skin . '/' . $theme;

    if (file_exists($skinPath)) {
        header('Content-Type: image/png');
        readfile($skinPath);
        exit;
    } else {
        Utilities::redirect('/assets/unknown_theme.png');
    }
}


// this is very ugly, i know.
#[NoReturn] function load_file_from_vendor($path, $content_type): void
{
    header("Content-Type: $content_type");
    readfile(BLUFF_VENDOR_PATH . $path);
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

$uri = parse_url(rawurldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
$path = explode('/', $uri);

// testing code
if ($isChazizSB) {
    /*
    // if the user is still logged in but isnt an admin, log them out.
    if ($auth->isUserLoggedIn() && !$auth->isUserAdmin()) {
    */

    // if the user is still logged in despite being banned, log them out.
    if ($auth->isUserLoggedIn() && $auth->getUserBanData()) {
        Utilities::logOutUser();
    }

    /*
    if (Utilities::getIpAddress() != "localhost" && !$auth->isUserLoggedIn() && $path[1] != "login") {
        Utilities::redirect("/login");
    }
    */
}

// its dynamic shit because i cant be arsed
function handle_debug_page_path(string $path): void
{
    $debug_pages_path = BLUFF_PRIVATE_PATH . '/pages/debug/';

    if (!$path) $path = "index";
    $path = str_replace(['..', '/', '\\'], '', $path);

    $full_path = $debug_pages_path . $path . ".php";

    if (file_exists($full_path) && str_starts_with(realpath($full_path), $debug_pages_path)) {
        require $full_path;
    } else {
        last_resort();
    }
}

// Originally based on Rollerozxa's router implementation in Principia-Web.
// https://github.com/principia-game/principia-web/blob/master/router.php

if (isset($path[1]) && $path[1] != '') {
    match ($path[1]) {
        'admin' => match ($path[2] ?? null) {
            'login' => require(BLUFF_PRIVATE_PATH . '/pages/admin_login.php'),
            'users' => match ($path[3] ?? null) {
                $path[3] ?? null => (!empty($path[3]) && $path[3] !== '')
                    ? require(BLUFF_PRIVATE_PATH . '/pages/admin_user_edit.php')
                    : require(BLUFF_PRIVATE_PATH . '/pages/admin_users.php'),
                default => require(BLUFF_PRIVATE_PATH . '/pages/admin_users.php'),
            },
            'overview' => require(BLUFF_PRIVATE_PATH . '/pages/admin_overview.php'),
            'uploads' => match ($path[3] ?? null) {
                $path[3] ?? null => (!empty($path[3]) && $path[3] !== '')
                    ? require(BLUFF_PRIVATE_PATH . '/pages/admin_upload_edit.php')
                    : require(BLUFF_PRIVATE_PATH . '/pages/admin_uploads.php'),
                default => require(BLUFF_PRIVATE_PATH . '/pages/admin_uploads.php'),
            },
            'interactions' => require(BLUFF_PRIVATE_PATH . '/pages/admin_interactions.php'),
            'invitekeys' => require(BLUFF_PRIVATE_PATH . '/pages/admin_invitekeys.php'),
            default => Utilities::redirect('/admin/overview/'),
        },
        'api' => match ($path[2] ?? null) {
            'biscuit' => match ($path[3] ?? null) {
                'commenting' => require(BLUFF_PRIVATE_PATH . '/pages/api/biscuit/commenting.php'),
                'submission_interaction' => require(BLUFF_PRIVATE_PATH . '/pages/api/biscuit/submission_interaction.php'),
                'user_interaction' => require(BLUFF_PRIVATE_PATH . '/pages/api/biscuit/user_interaction.php'),
                default => last_resort(),
            },
            'legacy' => match ($path[3] ?? null) {
                'ajax_watch' => require(BLUFF_PRIVATE_PATH . '/pages/api/legacy/ajax_watch.php'),
                'comment' => require(BLUFF_PRIVATE_PATH . '/pages/api/legacy/comment.php'),
                'rate' => require(BLUFF_PRIVATE_PATH . '/pages/api/legacy/rate.php'),
                'subscribe' => require(BLUFF_PRIVATE_PATH . '/pages/api/legacy/subscribe.php'),
                default => last_resort(),
            },
            'v3' => match ($path[3] ?? null) { //INCOMPLETE
                'get_comments' => require(BLUFF_PRIVATE_PATH . '/pages/api/v3/get_comments.php'),
                'get_instance_info' => require(BLUFF_PRIVATE_PATH . '/pages/api/v3/get_instance_info.php'),
                'get_upload' => require(BLUFF_PRIVATE_PATH . '/pages/api/v3/get_upload.php'),
                'get_uploads' => require(BLUFF_PRIVATE_PATH . '/pages/api/v3/get_uploads.php'),
                default => die(json_encode("Invalid API."))
            },
            default => last_resort(),
        },
        'assets' => match ($path[2] ?? null) {
            'bootstrap-icons.svg' => load_file_from_vendor('/twbs/bootstrap-icons/bootstrap-icons.svg', 'image/svg+xml'),
            'previews' => load_thumbnail_from_skin($path[3] ?? ''),
            default => last_resort(),
        },
        'browse' => require(BLUFF_PRIVATE_PATH . '/pages/browse.php'),
        'debug' => match ($path[2] ?? null) {
            //null, '', 'index' => require(BLUFF_PRIVATE_PATH . '/pages/debug/index.php'),
            //'notifications' => require(BLUFF_PRIVATE_PATH . '/pages/debug/notifications.php'),
            default => handle_debug_page_path($path[2] ?? ''),
        },
        'delete' => require(BLUFF_PRIVATE_PATH . '/pages/delete.php'),
        'design_test' => require(BLUFF_PRIVATE_PATH . '/pages/design_test.php'),
        'edit' => require(BLUFF_PRIVATE_PATH . '/pages/edit.php'),
        'feature' => require(BLUFF_PRIVATE_PATH . '/pages/feature.php'),
        'guidelines' => require(BLUFF_PRIVATE_PATH . '/pages/guidelines.php'),
        'help' => require(BLUFF_PRIVATE_PATH . '/pages/help.php'),
        'index' => require(BLUFF_PRIVATE_PATH . '/pages/index.php'),
        'journals' => require(BLUFF_PRIVATE_PATH . '/pages/journals.php'),
        'license' => require(BLUFF_PRIVATE_PATH . '/pages/license.php'),
        'login' => require(BLUFF_PRIVATE_PATH . '/pages/login.php'),
        'logout' => require(BLUFF_PRIVATE_PATH . '/pages/logout.php'),
        'my_submissions' => Utilities::redirect('/my_uploads'),
        'my_messages' => require(BLUFF_PRIVATE_PATH . '/pages/my_messages.php'),
        'my_uploads' => require(BLUFF_PRIVATE_PATH . '/pages/my_uploads.php'),
        'notices' => Utilities::redirect('/notifications'),
        'notifications' => require(BLUFF_PRIVATE_PATH . '/pages/notifications.php'),
        'privacy' => require(BLUFF_PRIVATE_PATH . '/pages/privacy.php'),
        'profile' => require(BLUFF_PRIVATE_PATH . '/pages/profile.php'),
        'read' => require(BLUFF_PRIVATE_PATH . '/pages/read.php'),
        'register' => require(BLUFF_PRIVATE_PATH . '/pages/register.php'),
        'search' => require(BLUFF_PRIVATE_PATH . '/pages/search.php'),
        'settings' => require(BLUFF_PRIVATE_PATH . '/pages/settings.php'),
        'staff' => require(BLUFF_PRIVATE_PATH . '/pages/staff.php'),
        'theme' => require(BLUFF_PRIVATE_PATH . '/pages/theme.php'),
        'tos' => require(BLUFF_PRIVATE_PATH . '/pages/tos.php'),
        'upload' => require(BLUFF_PRIVATE_PATH . '/pages/upload.php'),
        'user' => match ($_SERVER['HTTP_ACCEPT'] ?? null) {
            default => require(BLUFF_PRIVATE_PATH . '/pages/user.php')
        },
        'users' => require(BLUFF_PRIVATE_PATH . '/pages/users.php'),
        'verify_birthdate' => require(BLUFF_PRIVATE_PATH . '/pages/verify_birthdate.php'),
        'version' => require(BLUFF_PRIVATE_PATH . '/pages/version.php'),
        'view' => require(BLUFF_PRIVATE_PATH . '/pages/view.php'),
        'watch' => Utilities::redirect('/view/' . $_GET['v']),
        'write' => require(BLUFF_PRIVATE_PATH . '/pages/write.php'),
        default => last_resort()
    };
} else {
    require(BLUFF_PRIVATE_PATH . '/pages/index.php');
}
