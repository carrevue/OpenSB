#!/usr/bin/php
<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
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

global $sb, $database;

use Core\VersionNumber;

use Data\Upload\UploadFlags;

use DivisionByZeroError;
use Alchemy\BinaryDriver\Exception\ExecutionFailureException;

use FFMpeg\Coordinate;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Filters;
// use FFMpeg\Filters\Video\CustomFilter;
use FFMpeg\Format\Video\X264;
use FFMpeg\Exception\RuntimeException;

define("SB_ROOT_PATH", dirname(__DIR__, 2));
define("SB_PUBLIC_PATH", SB_ROOT_PATH . '/public'); // we need this for SquareBracketTwigExtension
define("SB_PRIVATE_PATH", SB_ROOT_PATH . '/private');
define("SB_VENDOR_PATH", SB_ROOT_PATH . '/vendor');
define("SB_GIT_PATH", SB_ROOT_PATH . '/.git'); // ONLY FOR makeVersionString() IN SquareBracket CLASS.

require_once SB_PRIVATE_PATH . '/common.php';

$supported_types = ['video', 'video_thumbnail_only', 'video_duration_only'];

function log(string $message): void
{
    $microtime = microtime(true);
    $timestamp = date('Y-m-d H:i:s', (int)$microtime) .
        sprintf('.%06d', ($microtime - floor($microtime)) * 1000000);
    echo $timestamp . ": " . $message . PHP_EOL;
}

function get_cpu_cores()
{
    if (PHP_OS_FAMILY == 'Windows') {
        $cores = shell_exec('echo %NUMBER_OF_PROCESSORS%');
    } else {
        $cores = shell_exec('nproc');
    }

    return (int)$cores ?? 1;
}

function downscale_video_for_thumbnail($videoWidth, $videoHeight, $targetWidth = 640): array
{
    // if video width smaller than target width, dont bother with downscaling.
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
    'ffmpeg.threads' => get_cpu_cores(),   // The number of threads that FFmpeg should use
    'ffmpeg.binaries' => 'ffmpeg',
    'ffprobe.binaries' => 'ffprobe',
];

// Here's an example of the required parameters for the upload processor:
// php private/scripts/upload_processor.php "videoid" "dynamic/videos/videoid.mp4" "video" "0"

if (!isset($argv[1])) {
    log("No parameters have been specified.");
}

log("Threads: " . get_cpu_cores());

$new = $argv[1];
$target_file = $argv[2];
$upload_type =  $argv[3];
$for_website = $argv[4] ?? 0;

log("Upload type: " .  $upload_type);

if (!in_array($upload_type, $supported_types)) {
    log("Unsupported type.");
    die();
}

try {
    $path = $sb->getStorageClass()->getPath();

    log("Storage path: " . $path);

    $ffmpeg = FFMpeg::create($config);
    $ffprobe = FFProbe::create($config);
    $h264 = new X264();

    $h264->setAudioKiloBitrate(320)->setAdditionalParameters(array('-ar', '44100'));

    log("File: " . $target_file);

    $video = $ffmpeg->open($target_file);

    log("Getting video data...");

    // STUPID FUCKING HACK THAT WILL MAKE THE SCRIPT PRONE TO BREAKAGE, which you can blame the php-ffmpeg devs for not
    // including any *proper* functionality to enable "count_frames" for ffprobe! we have to do this for every video in
    // case someone uploads one of those fuckass "1000000 hours" discord shitpost videos. -chaziz 12/28/2024
    $command = $config["ffprobe.binaries"] . " -v error -count_frames -select_streams v:0 -show_entries stream=nb_read_frames -of csv=p=0 " . $target_file;

    log("Attempting to get frame count...");
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

    if ($upload_type != "video_duration_only") {
        log("Resolution: " . $videoWidth . "x" . $videoHeight);
        log("Creating thumbnail...");

        // Thumbnail

        // calculate thumbnail resolution in a way that wont fuck up the aspect ratio
        $resolution = downscale_video_for_thumbnail($videoWidth, $videoHeight);

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

        log("Saving thumbnail...");

        //Thumbnails
        $frame->save($path . '/thumbnails/' . $new . '.png');

        log("Thumbnail saved!");

        if ($upload_type == "video_thumbnail_only") {
            log("Only processing thumbnail, exiting...");
            log("OpenSB Video Upload Processor Success!");

            if ($sb->isDiscordWebhookEnabled()) {
                $data = [
                    'id' => $new,
                ];

                $sb->getDiscordWebhookClass()->uploadProcessorSuccessHook($data);
            }

            die();
        }

        // Video

        // bitrate stuff
        $isHD = ($videoWidth >= 1280 || $videoHeight >= 720);
        $isFullHD = ($videoWidth >= 1920 || $videoHeight >= 1080);

        // calculate bitrate for video based on the resolution.
        if ($isFullHD) {
            $videoScaleFactor = min($videoWidth / 1920, $videoHeight / 1080);
            $bitrate = (int)min(4500, max(3000, 3000 * $videoScaleFactor));
        } elseif ($isHD) {
            $videoScaleFactor = min($videoWidth / 1280, $videoHeight / 720);
            $bitrate = (int)min(2500, max(1000, 1000 * $videoScaleFactor));
        } else {
            $videoScaleFactor = min($videoWidth / 640, $videoHeight / 360);
            $bitrate = (int)min(1200, max(600, 600 * $videoScaleFactor));
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
        $video->filters()->custom('scale=trunc(iw/2)*2:trunc(ih/2)*2');

        log("Converting video...");
        $video->save($h264, $path . '/videos/' . $new . '.converted.mp4');

        debug_print_backtrace();
        unlink($target_file);
    }

    if ($for_website) {
        log("Updating database...");
        $videoData = $database->fetch("SELECT v.* FROM uploads v WHERE v.upload_id = ?", [$new]);

        // if we couldnt get length just fallback to 0 seconds.
        if ($fucked) {
            $length = 0;
        } else {
            $length = round($duration / $framerate);
        }

        $database->query(
            "UPDATE uploads SET video_length = ?, flags = ? WHERE upload_id = ?",
            [$length, $videoData['flags'] &= ~UploadFlags::FLAG_UNPROCESSED->value, $new]
        );

        if ($upload_type != "video_duration_only") {
            if ($sb->isDiscordWebhookEnabled()) {
                $data = [
                    'id' => $new,
                ];

                $sb->getDiscordWebhookClass()->uploadProcessorSuccessHook($data);
            }
        }
    } else {
        log("Not a website video, skipping.");
    }
} catch (RuntimeException $e) {
    log("OpenSB Upload Processor Failure: " . $e->getMessage());

    // now try to get the ffmpeg error output
    $previous = $e->getPrevious();

    if ($previous instanceof ExecutionFailureException) {
        log($previous->getErrorOutput());
    }

    if ($sb->isDiscordWebhookEnabled()) {
        $data = [
            'id' => $new,
        ];

        $sb->getDiscordWebhookClass()->uploadProcessorFailHook($data);
    }

    clearstatcache();
    die();
}

log("OpenSB Upload Processor Success!");

clearstatcache();
