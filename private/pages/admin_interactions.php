<?php

namespace OpenSB;

global $auth, $twig, $orange;

use SquareBracket\Utilities;

if (!$auth->isUserAdmin()) {
    Utilities::notifyBanner("You do not have permission to access this page.", "/");
}

if (!$auth->hasUserAuthenticatedAsAnAdmin()) {
    Utilities::notifyBanner("Please login with your admin password.", "/admin/login");
}

if ($orange->getLocalOptions()["skin"] != "biscuit" && $orange->getLocalOptions()["skin"] != "charla") {
    Utilities::notifyBanner("Please change your skin to Biscuit.", "/theme");
}

echo $twig->render("admin_temporary.twig");