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

namespace Core;

use Data\Upload\UploadTypeEnum;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * class Storage
 * 
 * Handles storage. This was originally written to accomodate BunnyCDN support
 * while keeping filesystem support intact, however this was removed in OpenSB
 * 1.2.5.
 */
class Storage
{
    /**
     * @var SquareBracket The core OpenSB class.
     */
    private SquareBracket $sb;

    /**
     * @var Database The database class.
     */
    private Database $database;

    /**
     * @var bool If assets are disabled.
     */
    private bool $disabled;

    /**
     * @var string The path.
     */
    private string $path;

    /**
     * function __construct
     *
     * @param SquareBracket $sb
     * @param string|null $path The path
     *
     */
    public function __construct(SquareBracket $sb, ?string $path = null)
    {
        $default_path = SB_ROOT_PATH . '/dynamic'; // equivalent to SB_DYNAMIC_PATH in opensb 1.1-2.0

        $this->sb = $sb;
        $this->database = $sb->getDatabaseClass();
        $this->disabled = $sb->isAssetsDisabled();
        $this->path = $path ?? $default_path;
    }

    /**
     * function processVideoUpload
     * 
     * Returns the storage path.
     * 
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * function processVideoUpload
     * 
     * Starts the upload processor script on another process, which wraps 
     * around FFmpeg.
     *
     * @param string $new The upload ID
     * @param string $target_file The upload target file
     * @param string $type The upload type
     *
     * @return void
     */
    public function processVideoUpload(string $new, string $target_file, string $type = "video"): void
    {
        // on QA, we'll have to put them in prod's /dynamic/videos for now
        if ($this->sb->isTestInstance()) {
            $log_path = $this->path . '/videos/' . $new . '.log';
        } else {
            $log_path = SB_PRIVATE_PATH . '/upload_processor_logs/' . $new . '.log';
        }

        // this uses the version of php on path. if the upload processor errors
        // out with "OpenSB is not compatible with your PHP version.", then 
        // your path's php is too old.
        if (PHP_OS_FAMILY == 'Windows') {
            pclose(popen(sprintf(
                'start /B  php %s "%s" "%s" "$s" "1" > %s',
                SB_PRIVATE_PATH . '\scripts\upload_processor.php',
                $new,
                $target_file,
                $type,
                $log_path
            ), "r"));
        } else {
            system(sprintf(
                'php %s "%s" "%s" "%s" "1" > %s 2>&1 &',
                SB_PRIVATE_PATH . '/scripts/upload_processor.php',
                $new,
                $target_file,
                $type,
                $log_path
            ));
        }
    }

    /**
     * function processImageUpload
     * 
     * Downscales images to a width of 4096 pixels, and also downscales 
     * it to 640 pixels for the thumbnail.
     *
     * @param string $temp_name
     * @param string $new
     *
     * @return void
     */
    public function processImageUpload(string $temp_name, string $new): void
    {
        $target_file = $this->path . '/art/' . $new . '.png';
        $target_thumbnail = $this->path . '/art_thumbnails/' . $new . '.jpg';

        // image upload
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(4096);
        $img->toPng()->save($target_file);

        // thumbnail
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(640);
        $img->toJpeg(90)->save($target_thumbnail);

        unlink($temp_name);
    }

    /**
     * function processProfilePicture
     * 
     * Downscales profile pictures to a width and height of 512.
     *
     * @param string $temp_name
     * @param string $new
     *
     * @return void
     */
    public function processProfilePicture(string $temp_name, string $new): void
    {
        $target_file = $this->path . '/pfp/' . $new . '.png';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        // i have to do this otherwise non-1:1 images that are smaller than 512x512 won't be stretched
        $img->resize(512, 512);
        $img->toPng()->save($target_file);

        unlink($temp_name);
    }

    /**
     * function processCustomUploadThumbnail
     * 
     * Downscales thumbnails to a width of 640.
     *
     * @param string $temp_name
     * @param string $new
     *
     * @return void
     */
    public function processCustomUploadThumbnail(string $temp_name, string $new): void
    {
        $target_file = $this->path . '/custom_thumbnails/' . $new . '.jpg';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(640);
        $img->toJpeg(90)->save($target_file);

        unlink($temp_name);
    }

    /**
     * function processTriniumProfileBanner
     * 
     * Scales Trinium profile banners to a height of 300 pixels.
     *
     * @param string $temp_name
     * @param string $new
     *
     * @return void
     */
    public function processTriniumProfileBanner(string $temp_name, string $new): void
    {
        $target_file = $this->path . '/banners/' . $new . '.png';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scale(height: 300);
        $img->toPng()->save($target_file);

        unlink($temp_name);
    }

    /**
     * function processFinaliumProfileBanner
     * 
     * Scales Finalium profile banners.
     *
     * @param string $temp_name
     * @param string $new
     *
     * @return void
     */
    public function processFinaliumProfileBanner(string $temp_name, string $new): void
    {
        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);

        // save backup for later
        $img->toPng()->save($this->path . '/banners_finalium/' . $new . '@backup.png');

        // force images to be in 16:9
        $w = $img->width();
        $h = $img->height();
        if ($w / $h !== 16 / 9) {
            $targetH = (int) round($w * 9 / 16);
            $targetW = $w;
            if ($targetH > $h) {
                $targetW = (int) round($h * 16 / 9);
                $targetH = $h;
            }
            $img->crop(
                width:    $targetW,
                height:   $targetH,
                offset_x: (int) round(($w - $targetW) / 2),
                offset_y: (int) round(($h - $targetH) / 2),
            );
        }

        // based on the parameters of google's image processing url:
        // fcrop64=1,00005a57ffffa5a8
        $h  = $img->height();
        $y1 = (int) round((0x5a57 / 0xFFFF) * $h);
        $y2 = (int) round((0xa5a8 / 0xFFFF) * $h);
        $img->crop(
            width:    $img->width(),
            height:   $y2 - $y1,
            offset_x: 0,
            offset_y: $y1,
        );

        // save as 1060px and 2120px (for hidpi)
        (clone $img)->scaleDown(width: 1060)->toPng()->save($this->path . '/banners_finalium/' . $new . '.png');
        (clone $img)->scaleDown(width: 2120)->toPng()->save($this->path . '/banners_finalium/' . $new . '@2x.png');

        unlink($temp_name);
    }

    /**
     * function deleteUploadFile
     * 
     * Deletes the upload's file.
     *
     * @param array $data The upload array
     *
     * @note This is kind of weird, and will be reworked soon.
     *
     * @return void
     */
    public function deleteUploadFile(array $data): void
    {
        unlink(SB_ROOT_PATH . $data["upload_file"]);
    }

    /**
     * function getUploadThumbnail
     * 
     * Returns the upload thumbnail.
     *
     * @param string $id
     * @param int $type
     * @param bool $custom
     *
     * @return ?string
     */
    public function getUploadThumbnail($id, $type, $custom): ?string
    {
        $data = null;

        if ($type == UploadTypeEnum::Video->value) {
            $data = $this->getVideoUploadThumbnail($id, $custom);
        }
        if ($type == UploadTypeEnum::Image->value) {
            $data = $this->getImageUploadThumbnail($id, $custom);
        }

        return $data;
    }

    /**
     * function getUserProfilePicture
     *
     * Return the user profile picture.
     *
     * @param int $user User's ID
     * @param bool $isStaff Admin-specific behavior
     *
     * @return string
     */
    public function getUserProfilePicture(int $user, bool $isStaff = false): string
    {
        $placeholder = $this->sb->isHitchhiker() ? "profiledef_hitchhiker.svg" : "profiledef.svg";

        if ($this->disabled) return '/assets/' . $placeholder;

        $path = $this->path . '/pfp/' . $user . '.png';

        // don't bother with userdata since that might slow shit down
        $is_banned = $this->database->fetch("SELECT * FROM user_bans WHERE user = ?", [$user]);

        if ($is_banned && !$isStaff) {
            return '/assets/' . $placeholder;
        }

        if (file_exists($path)) {
            return '/dynamic/pfp/' . $user . '.png';
        }

        return '/assets/' . $placeholder;
    }

    /**
     * function getUserProfileBanner
     * 
     * Returns the user profile banner.
     *
     * @param int $user User's ID
     *
     * @return array
     */
    public function getUserProfileBanner(int $user): array
    {
        $default_finalium = $this->sb->isHitchhiker() ? "/assets/default_banner.svg" : false;

        $path_trinium = '/banners/' . $user . '.png';
        $path_finalium = '/banners_finalium/' . $user . '.png';
        $path_finalium2x = '/banners_finalium/' . $user . '@2x.png';

        $fuckass_array = [
            "trinium" => file_exists($this->path . $path_trinium) ? '/dynamic' . $path_trinium : false,
            "finalium" => file_exists($this->path . $path_finalium) ? '/dynamic' . $path_finalium : $default_finalium,
            "finalium_2x" => file_exists($this->path . $path_finalium2x) ? '/dynamic' . $path_finalium2x : $default_finalium,
        ];

        return $fuckass_array;
    }

    /**
     * function getVideoUploadThumbnail
     * 
     * Returns the video upload thumbnail.
     *
     * @param string $id
     * @param bool $custom
     *
     * @return string
     */
    private function getVideoUploadThumbnail(string $id, bool $custom): string
    {
        $placeholder = $this->sb->isHitchhiker() ? "placeholder_hitchhiker.svg" : "placeholder_video.svg";

        if ($this->disabled) return '/assets/' . $placeholder;

        return $this->getThumbnailPath(
            $id,
            $custom,
            'thumbnails',
            'png',
            $placeholder
        );
    }

    /**
     * function getImageUploadThumbnail
     * 
     * Returns the image upload thumbnail.
     *
     * @param string $id
     * @param bool $custom
     *
     * @return string
     */
    private function getImageUploadThumbnail(string $id, bool $custom): string
    {
        $placeholder = $this->sb->isHitchhiker() ? "placeholder_hitchhiker.svg" : "placeholder_image.svg";

        if ($this->disabled) return '/assets/' . $placeholder;

        return $this->getThumbnailPath(
            $id,
            $custom,
            'art_thumbnails',
            'jpg',
            $placeholder
        );
    }

    /**
     * function getThumbnailPath
     *
     * @param string $id
     * @param bool $custom
     * @param string $defaultFolder
     * @param string $defaultExtension
     * @param string $fallback
     *
     * @return string
     */
    private function getThumbnailPath(
        string $id,
        ?bool $custom,
        string $defaultFolder,
        string $defaultExtension,
        string $fallback
    ): string {
        $customPath = $this->path . '/custom_thumbnails/' . $id . '.jpg';
        $defaultPath = $this->path . '/' . $defaultFolder . '/' . $id . '.' . $defaultExtension;

        // if custom thumbnail exists then use that
        if ($custom && file_exists($customPath)) {
            return '/dynamic/custom_thumbnails/' . $id . '.jpg';
        }

        if (file_exists($defaultPath)) {
            return '/dynamic/' . $defaultFolder . '/' . $id . '.' . $defaultExtension;
        }

        return '/assets/' . $fallback;
    }
}
