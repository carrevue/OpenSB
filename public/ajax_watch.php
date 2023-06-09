<?php

namespace openSB;

require_once dirname(__DIR__) . '/private/class/common.php';
if (isset($_POST['limit'])) {
    $limit = (isset($_POST['limit']) ? $_POST['limit'] : 6);
    $offset = (isset($_POST['from']) ? $_POST['from'] : 0);
    $user = (isset($_POST['user']) ? $_POST['user'] : 0);

    $videoData = $sql->query("SELECT $userfields $videofields FROM videos v JOIN users u ON v.author = u.id WHERE v.video_id NOT IN (SELECT submission FROM takedowns) ORDER BY v.id DESC LIMIT ? OFFSET ?", [$limit, $offset]);

    $twig = twigloader();
    echo $twig->render('components/videolist.twig', [
        'videos' => $videoData,
    ]);
}
