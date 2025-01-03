<?php

namespace SquareBracket;

class Storage
{
    private Database $database;
    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function processVideo($new, $target_file): void
    {
        // this uses the version of php on path. if processing worker errors out with"OpenSB is not compatible
        // with your PHP version.", then your path's php is too old.
        if (str_starts_with(php_uname(), "Windows")) {
            pclose(popen(sprintf('start /B  php %s "%s" "%s" "1" > %s', SB_PRIVATE_PATH . '\scripts\processingworker.php', $new, $target_file, SB_DYNAMIC_PATH . '/videos/' . $new . '.log'), "r"));
        } else {
            system(sprintf('php %s "%s" "%s" "1" > %s 2>&1 &', SB_PRIVATE_PATH . '/scripts/processingworker.php', $new, $target_file, SB_DYNAMIC_PATH . '/videos/' . $new . '.log'));
        }
    }

    public function getVideoThumbnail($id, $custom): string
    {
        global $branding;

        if ($custom && file_exists(SB_DYNAMIC_PATH . '/custom_thumbnails/' . $id . '.jpg')) {
            return '/dynamic/custom_thumbnails/' . $id . '.jpg';
        } elseif (file_exists(SB_DYNAMIC_PATH . '/thumbnails/' . $id . '.png')) {
            return '/dynamic/thumbnails/' . $id . '.png';
        } else {
            return $branding["assets_location"] . '/placeholder.png';
        }
    }

    public function getImageThumbnail($id, $custom): string
    {
        global $branding;

        if ($custom && file_exists(SB_DYNAMIC_PATH . '/custom_thumbnails/' . $id . '.jpg')) {
            return '/dynamic/custom_thumbnails/' . $id . '.jpg';
        }
        elseif (file_exists(SB_DYNAMIC_PATH . '/art_thumbnails/' . $id . '.jpg')) {
            return'/dynamic/art_thumbnails/' . $id . '.jpg';
        } else {
            return $branding["assets_location"] . '/placeholder.png';
        }
    }

    // this is a leftover of when opensb used to support bunnycdn in 2023-2024.
    public function fileExists($file): bool
    {
        return file_exists($file);
    }

    public function processImage($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/art/' . $new . '.png';
        $target_thumbnail = SB_DYNAMIC_PATH . '/art_thumbnails/' . $new . '.jpg';

        Utilities::processImageUploadFile($temp_name, $target_file);
        Utilities::processImageUploadThumbnail($temp_name, $target_thumbnail);

        unlink($temp_name);
    }

    public function uploadProfilePicture($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/pfp/' . $new . '.png';

        Utilities::processProfilePicture($temp_name, $target_file);

        unlink($temp_name);
    }

    public function uploadCustomThumbnail($temp_name, $new): void
    {
        Utilities::processCustomUploadThumbnail($temp_name, $new);

        unlink($temp_name);
    }

    public function uploadProfileBanner($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/banners/' . $new . '.png';

        Utilities::processProfileBanner($temp_name, $target_file);

        unlink($temp_name);
    }

    public function deleteSubmission($data): void
    {
        unlink(SB_ROOT_PATH . $data["videofile"]);
    }
}