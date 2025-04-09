<?php

namespace SquareBracket;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class Storage
{
    private Database $database;
    public function __construct(Database $database) {
        $this->database = $database;
    }

    public function processVideoUpload($new, $target_file): void
    {
        // this uses the version of php on path. if processing worker errors out with"OpenSB is not compatible
        // with your PHP version.", then your path's php is too old.
        if (str_starts_with(php_uname(), "Windows")) {
            pclose(popen(sprintf('start /B  php %s "%s" "%s" "1" > %s', SB_PRIVATE_PATH . '\scripts\processingworker.php', $new, $target_file, SB_DYNAMIC_PATH . '/videos/' . $new . '.log'), "r"));
        } else {
            system(sprintf('php %s "%s" "%s" "1" > %s 2>&1 &', SB_PRIVATE_PATH . '/scripts/processingworker.php', $new, $target_file, SB_DYNAMIC_PATH . '/videos/' . $new . '.log'));
        }
    }

    public function getVideoUploadThumbnail($id, $custom): string
    {
        global $branding;

        if ($custom && file_exists(SB_DYNAMIC_PATH . '/custom_thumbnails/' . $id . '.jpg')) {
            return '/dynamic/custom_thumbnails/' . $id . '.jpg';
        } elseif (file_exists(SB_DYNAMIC_PATH . '/thumbnails/' . $id . '.png')) {
            return '/dynamic/thumbnails/' . $id . '.png';
        } else {
            return '/assets/placeholder_video.svg';
        }
    }

    public function getImageUploadThumbnail($id, $custom): string
    {
        global $branding;

        if ($custom && file_exists(SB_DYNAMIC_PATH . '/custom_thumbnails/' . $id . '.jpg')) {
            return '/dynamic/custom_thumbnails/' . $id . '.jpg';
        }
        elseif (file_exists(SB_DYNAMIC_PATH . '/art_thumbnails/' . $id . '.jpg')) {
            return '/dynamic/art_thumbnails/' . $id . '.jpg';
        } else {
            return '/assets/placeholder_image.svg';
        }
    }

    public function processImageUpload($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/art/' . $new . '.png';
        $target_thumbnail = SB_DYNAMIC_PATH . '/art_thumbnails/' . $new . '.jpg';

        // image upload
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(4096);
        $img->toPng()->save($target_file);

        // thumbnail
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(512);
        $img->toJpeg(90)->save($target_thumbnail);

        unlink($temp_name);
    }

    public function processProfilePicture($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/pfp/' . $new . '.png';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        // i have to do this otherwise non-1:1 images that are smaller than 512x512 won't be stretched
        $img->resize(512, 512);
        $img->toPng()->save($target_file);

        unlink($temp_name);
    }

    public function processCustomUploadThumbnail($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/custom_thumbnails/' . $new . '.jpg';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(512);
        $img->toJpeg(80)->save($target_file);

        unlink($temp_name);
    }

    public function processProfileBanner($temp_name, $new): void
    {
        $target_file = SB_DYNAMIC_PATH . '/banners/' . $new . '.png';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->resizeDown(height: 323);
        $img->toPng()->save($target_file);

        unlink($temp_name);
    }

    public function deleteUploadFile($data): void
    {
        unlink(SB_ROOT_PATH . $data["videofile"]);
    }
}