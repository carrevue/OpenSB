<?php

namespace openSB;

require_once dirname(__DIR__) . '/private/class/common.php';

$stats = $sql->fetch("SELECT (SELECT COUNT(*) FROM users) usercount, (SELECT COUNT(*) FROM videos) videocount, (SELECT COUNT(*) FROM views) viewcount, (SELECT COUNT(*) FROM comments) commentcount");

$twig = twigloader();
echo $twig->render('stats.twig', [
    'stats' => $stats,
]);
