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

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

use BluffingoCore\Database;

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
     * function __construct
     *
     * @param SquareBracket $sb
     *
     * @return void
     */
    public function __construct(SquareBracket $sb)
    {
        $this->sb = $sb;
        $this->database = $sb->getDatabaseClass();
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
    public function processVideoUpload(string $new, string $target_file, $type = "video"): void
    {
        // this uses the version of php on path. if the upload processor errors
        // out with "OpenSB is not compatible with your PHP version.", then 
        // your path's php is too old.
        if (str_starts_with(php_uname(), "Windows")) {
            pclose(popen(sprintf(
                'start /B  php %s "%s" "%s" "$s" "1" > %s',
                BLUFF_PRIVATE_PATH . '\scripts\upload_processor.php',
                $new,
                $target_file,
                $type,
                BLUFF_DYNAMIC_PATH . '/videos/' . $new . '.log'
            ), "r"));
        } else {
            system(sprintf(
                'php %s "%s" "%s" "%s" "1" > %s 2>&1 &',
                BLUFF_PRIVATE_PATH . '/scripts/upload_processor.php',
                $new,
                $target_file,
                $type,
                BLUFF_DYNAMIC_PATH . '/videos/' . $new . '.log'
            ));
        }
    }

    /**
     * function processImageUpload
     * 
     * Downscales images to a width of 4096 pixels, and also downscales 
     * it to 640 pixels for the thumbnail.
     *
     * @param mixed $temp_name
     * @param mixed $new
     *
     * @return void
     */
    public function processImageUpload($temp_name, $new): void
    {
        $target_file = BLUFF_DYNAMIC_PATH . '/art/' . $new . '.png';
        $target_thumbnail = BLUFF_DYNAMIC_PATH . '/art_thumbnails/' . $new . '.jpg';

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
     * @param mixed $temp_name
     * @param mixed $new
     *
     * @return void
     */
    public function processProfilePicture($temp_name, $new): void
    {
        $target_file = BLUFF_DYNAMIC_PATH . '/pfp/' . $new . '.png';

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
     * @param mixed $temp_name
     * @param mixed $new
     *
     * @return void
     */
    public function processCustomUploadThumbnail($temp_name, $new): void
    {
        $target_file = BLUFF_DYNAMIC_PATH . '/custom_thumbnails/' . $new . '.jpg';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scaleDown(640);
        $img->toJpeg(90)->save($target_file);

        unlink($temp_name);
    }

    /**
     * function processProfileBanner
     * 
     * Scales profile banners to a height of 300 pixels
     *
     * @param mixed $temp_name
     * @param mixed $new
     *
     * @return void
     */
    public function processProfileBanner($temp_name, $new): void
    {
        $target_file = BLUFF_DYNAMIC_PATH . '/banners/' . $new . '.png';

        $manager = new ImageManager(Driver::class);
        $img = $manager->read($temp_name);
        $img->scale(height: 300);
        $img->toPng()->save($target_file);

        unlink($temp_name);
    }

    /**
     * function deleteUploadFile
     * 
     * Deletes the upload's file.
     *
     * @param mixed $data
     *
     * @return void
     */
    public function deleteUploadFile($data): void
    {
        unlink(BLUFF_ROOT_PATH . $data["upload_file"]);
    }

    /**
     * function getVideoUploadThumbnail
     * 
     * Returns the video upload thumbnail.
     *
     * @param mixed $id
     * @param mixed $custom
     *
     * @return string
     */
    public function getVideoUploadThumbnail($id, $custom): string
    {
        $placeholder = $this->sb->isFulpTube() ? "placeholder_hitchhiker.svg" : "placeholder_video.svg";

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
     * @param mixed $id
     * @param mixed $custom
     *
     * @return string
     */
    public function getImageUploadThumbnail($id, $custom): string
    {
        $placeholder = $this->sb->isFulpTube() ? "placeholder_hitchhiker.svg" : "placeholder_image.svg";

        return $this->getThumbnailPath(
            $id,
            $custom,
            'art_thumbnails',
            'jpg',
            $placeholder
        );
    }

    /**
     * function getUserProfilePicture
     * 
     * Return the user profile picture.
     *
     * @param mixed $username
     * @param mixed $isAdmin
     *
     * @return string
     */
    public function getUserProfilePicture($username, $isAdmin): string
    {
        $placeholder = $this->sb->isFulpTube() ? "profiledef_hitchhiker.svg" : "profiledef.svg";

        $id = Utilities::usernameToUserID($this->database, $username);

        $path = BLUFF_DYNAMIC_PATH . '/pfp/' . $id . '.png';

        // don't bother with userdata since that might slow shit down
        $is_banned = $this->database->fetch("SELECT * FROM user_bans WHERE user = ?", [$id]);

        if ($is_banned && !$isAdmin) {
            return '/assets/' . $placeholder;
        }

        if (file_exists($path)) {
            return '/dynamic/pfp/' . $id . '.png';
        }

        return '/assets/' . $placeholder;
    }

    /**
     * function getUserProfileBanner
     * 
     * Returns the user profile banner.
     *
     * @param mixed $username
     *
     * @return bool|string
     */
    public function getUserProfileBanner($username): bool|string
    {
        $id = Utilities::usernameToUserID($this->database, $username);

        $path = BLUFF_DYNAMIC_PATH . '/banners/' . $id . '.png';

        if (file_exists($path)) {
            return '/dynamic/banners/' . $id . '.png';
        } else {
            //$data = "/assets/default_banner.svg"; this does not look good with profile customization
            return false;
        }
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
        $customPath = BLUFF_DYNAMIC_PATH . '/custom_thumbnails/' . $id . '.jpg';
        $defaultPath = BLUFF_DYNAMIC_PATH . '/' . $defaultFolder . '/' . $id . '.' . $defaultExtension;

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
