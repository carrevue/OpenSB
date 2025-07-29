#!/usr/bin/php
<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2025 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou

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

global $database;

use SquareBracket\VersionNumber;

use DivisionByZeroError;
use Alchemy\BinaryDriver\Exception\ExecutionFailureException;

use FFMpeg\Coordinate;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters;
// use FFMpeg\Filters\Video\CustomFilter;
use FFMpeg\Format\Video\X264;
use FFMpeg\Exception\RuntimeException;

define("BLUFF_ROOT_PATH", dirname(__DIR__, 2));
define("BLUFF_DYNAMIC_PATH", BLUFF_ROOT_PATH . '/dynamic');
define("BLUFF_PUBLIC_PATH", BLUFF_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("BLUFF_PRIVATE_PATH", BLUFF_ROOT_PATH . '/private');
define("BLUFF_VENDOR_PATH", BLUFF_ROOT_PATH . '/vendor');
define("BLUFF_GIT_PATH", BLUFF_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once BLUFF_PRIVATE_PATH . '/common.php';

function log(string $message): void
{
    $microtime = microtime(true);
    $timestamp = date('Y-m-d H:i:s', (int)$microtime) .
        sprintf('.%06d', ($microtime - floor($microtime)) * 1000000);
    echo $timestamp . ": " . $message . PHP_EOL;
}

function downscaleVideoForThumbnail($videoWidth, $videoHeight): array
{
    $targetWidth = 640;

    // if video width smaller than 640, dont bother with downscaling.
    if ($videoWidth <= $targetWidth) {
        return ['width' => $videoWidth, 'height' => $videoHeight];
    }

    // otherwise, downscale.
    $scaleFactor = $targetWidth / $videoWidth;
    $newHeight = round($videoHeight * $scaleFactor);

    return ['width' => $targetWidth, 'height' => $newHeight];
}

echo (new VersionNumber)->outputVersionBanner();

// this is hardcoded, Fuck.
$config = [
    'timeout' => 3600, // The timeout for the underlying process (1 hour)
    'ffmpeg.threads' => 12,   // The number of threads that FFmpeg should use
    'ffmpeg.binaries' => 'ffmpeg',
    'ffprobe.binaries' => 'ffprobe',
];

// Here's an example of the required parameters for the processing worker:
// php private/scripts/processingworker.php "videoid" "dynamic/videos/videoid.mp4" "video" "0"

if (!isset($argv[1])) {
    log("No parameters have been specified.");
}

$new = $argv[1];
$target_file = $argv[2];
$upload_type =  $argv[3];
$for_website = $argv[4];

log("Upload type: " .  $upload_type);

try {
    $ffmpeg = FFMpeg::create($config);
    $ffprobe = FFProbe::create($config);
    $h264 = new X264();

    $h264->setAudioKiloBitrate(320)->setAdditionalParameters(array('-ar', '44100'));

    $video = $ffmpeg->open($target_file);

    log("Getting video data...");

    // STUPID FUCKING HACK THAT WILL MAKE THE SCRIPT PRONE TO BREAKAGE, which you can blame the php-ffmpeg devs for not
    // including any *proper* functionality to enable "count_frames" for ffprobe! we have to do this for every video in
    // case someone uploads one of those fuckass "1000000 hours" discord shitpost videos. -chaziz 12/28/2024
    $command = $config["ffprobe.binaries"] . " -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of csv=p=0 " . $target_file;

    log("Command: " . $command);
    $duration_command = shell_exec($command);

    $duration = trim($duration_command);
    $fucked = false;

    if (is_numeric($duration)) {
        log("Frame count: " . $duration);
    } else {
        log("Unable to determine frame count.");
        $fucked = true;
    }

    if ($fucked) {
        log("Falling back to nb_frames");

        // get frame count the bad way.
        $duration = $ffprobe
            ->streams($target_file)    // extracts file information
            ->videos()              // filters video streams
            ->first()               // returns the first video stream
            ->get('nb_frames');    // returns the duration property
    }

    //get fractional framerate
    $fracFramerate = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("avg_frame_rate");

    // get width
    $videoWidth = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("width");

    // get height
    $videoHeight = $ffprobe
        ->streams($target_file)    // extracts file information
        ->videos()              // filters video streams
        ->first()               // returns the first video stream
        ->get("height");

    // attempt to get the actual framerate
    try {
        $framerate = explode("/", $fracFramerate)[0] / explode("/", $fracFramerate)[1];
        log("Framerate: " . $fracFramerate);
    } catch (DivisionByZeroError) {
        log("Failed to get framerate.");
        $fucked = true;
    }

    log("Resolution: " . $videoWidth . "x" . $videoHeight);
    log("Creating thumbnail...");

    // Thumbnail

    // calculate thumbnail resolution in a way that wont fuck up the aspect ratio
    $resolution = downscaleVideoForThumbnail($videoWidth, $videoHeight);

    log("Resolution for thumbnail: " . $resolution["width"] . "x" . $resolution["height"]);

    if ($fucked) {
        log("Taking thumbnail from first frame");
        $frame = $video->frame(new Coordinate\TimeCode(0, 0, 0, 1));
    } else {
        // this is fucked. look into this later. -chaziz 6/9/2025
        /*
        log("Figuring out thumbnail");
        //$cloned_video_for_thumbnail = clone $video;
        $cloned_video_for_thumbnail = $ffmpeg->open($target_file);
        $cloned_video_for_thumbnail->addFilter(new CustomFilter(
            'select=gt(scene\,0.1),' .
            'thumbnail=n=50,' .
            'blackframe=0'
        ));
        $frame = $cloned_video_for_thumbnail->frame(Coordinate\TimeCode::fromSeconds($thumbnailTime / $framerate));
        */

        $thumbnailTime = $duration * 0.33;
        log("Taking thumbnail from frame " . $thumbnailTime);

        $frame = $video->frame(Coordinate\TimeCode::fromSeconds($thumbnailTime / $framerate));
    }
    $frame->filters()->custom('scale=' . $resolution["width"] . 'x' . $resolution["height"]);
    log("Saving thumbnail");
    $frame->save(BLUFF_DYNAMIC_PATH . '/thumbnails/' . $new . '.png');
    log("Thumbnail saved!");

    // Video

    // bitrate stuff
    $isHD = ($videoWidth >= 1280 || $videoHeight >= 720);
    $isFullHD = ($videoWidth >= 1920 || $videoHeight >= 1080);

    if ($isFullHD) {
        $bitrate = 10000;
    } elseif ($isHD) {
        $videoScaleFactor = min($videoWidth / 1920, $videoHeight / 1080);
        $bitrate = (int)max(5000, min(10000, 5000 + (5000 * $videoScaleFactor)));
    } else {
        // calculate bitrate for video based on the resolution.
        $videoScaleFactor = min($videoWidth / 1280, $videoHeight / 720);
        $bitrate = (int)max(1000, 5000 * $videoScaleFactor);
    }

    log("Video bitrate: " . $bitrate);

    // if the video is higher than 1920x1080 then scale it down to 1080p.
    if ($videoWidth > 1920 || $videoHeight > 1080) {
        log("Scaling down video to 1080p.");
        $video->filters()->resize(
            new Coordinate\Dimension(1920, 1080),
            Filters\Video\ResizeFilter::RESIZEMODE_INSET,
            true
        );
    }

    $h264->setKiloBitrate($bitrate);

    $video->filters()->custom('format=yuv420p');

    log("Converting video");
    $video->save($h264, BLUFF_DYNAMIC_PATH . '/videos/' . $new . '.converted.mp4');

    debug_print_backtrace();
    unlink($target_file);

    if ($for_website) {
        log("Updating database...");
        $videoData = $database->fetch("SELECT v.* FROM uploads v WHERE v.video_id = ?", [$new]);

        // if we couldnt get length just fallback to 0 seconds.
        if ($fucked) {
            $length = 0;
        } else {
            $length = round($duration / $framerate);
        }

        $database->query(
            "UPDATE uploads SET videolength = ?, flags = ? WHERE video_id = ?",
            [$length, $videoData['flags'] ^ 0x2, $new]
        );

        if ($orange->isDiscordWebhookEnabled()) {
            $data = [
                'id' => $new,
            ];

            $orange->getDiscordWebhookClass()->uploadProcessingWorkerSuccessHook($data);
        }
    } else {
        log("Not a website video, skipping.");
    }
} catch (RuntimeException $e) {
    log("OpenSB Video Processing Worker Failure: " . $e->getMessage());

    // now try to get the ffmpeg error output
    $previous = $e->getPrevious();

    if ($previous instanceof ExecutionFailureException) {
        log($previous->getErrorOutput());
    }

    if ($orange->isDiscordWebhookEnabled()) {
        $data = [
            'id' => $new,
        ];

        $orange->getDiscordWebhookClass()->uploadProcessingWorkerFailHook($data);
    }

    clearstatcache();
    die();
}

log("OpenSB Video Processing Worker Success!");

clearstatcache();
