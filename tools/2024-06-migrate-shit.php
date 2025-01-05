<?php
namespace OpenSB;

global $database, $storage, $isChazizSB;

define("SB_ROOT_PATH", dirname(__DIR__));
define("SB_DYNAMIC_PATH", SB_ROOT_PATH . '/dynamic');
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once dirname(__DIR__) . '/private/class/common.php';

if (!$isChazizSB) { die("The isChazizSB variable MUST be enabled."); }

$pendingVideosArray = $database->fetchArray($database->query("SELECT video_id FROM videos WHERE videofile = 'TODO_BY_SCRIPT'"));

$pendingVideos = array_map(function($row) {
    return $row['video_id'];
}, $pendingVideosArray);

$directory = SB_DYNAMIC_PATH . '/videos';
$convertedVideos = [];

if (is_dir($directory)) {
    if ($dh = opendir($directory)) {
        while (($file = readdir($dh)) !== false) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'mp4' && str_contains($file, '.converted.mp4')) {
                $convertedVideos[] = str_replace('.converted.mp4', '', $file);
            }
        }
        closedir($dh);
    } else {
        echo "Could not open the directory.";
    }
} else {
    echo "The directory does not exist. Fail.";
}

// remove videos already uploaded over to bunnycdn.
$convertedVideos = array_filter($convertedVideos, function($videoId) use ($pendingVideos) {
    return in_array($videoId, $pendingVideos);
});

var_dump($convertedVideos);

// if this fails during a video, edit default_socket_timeout in php.ini to a higher value.
foreach ($convertedVideos as $video) {
    echo $video . PHP_EOL;
    $storage->processVideoUpload($video, $directory . '/' . $video . '.converted.mp4');
}