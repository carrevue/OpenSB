<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2021-2026 Chaziz
  Copyright (C) 2021 ROllerozxa
  Copyright (C) 2021-2022 icanttellyou
  Copyright (C) 2021 Veselcraft

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

namespace Pages;

global $sb, $twig, $auth;

use \Exception;

use Actions\ActionError;
use Actions\UploadActions;
use Core\Utilities;
use Data\Upload\UploadVisibilityEnum;

// tip: if youre hosting opensb on a linux distro with selinux included (eg: fedora) and you get some
// kind of access denied error. run these commands as root/sudo:
// semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/opensb/dynamic(/.*)?"
// restorecon -Rv /var/www/opensb/dynamic
// -chaziz 4/20/2025

$actions = new UploadActions($sb);

$error = $actions->canUpload();

if ($error instanceof ActionError) {
    match ($error) {
        ActionError::LoginRequired => Utilities::notifyBanner($error->value, "/login"),
        ActionError::UploadRatelimit => Utilities::notifyBanner($error->value, "/", "warning", [$actions->uploadRateLimit() / 60]),
        default => Utilities::notifyBanner($error->value, "/"),
    };
}

if (isset($_POST['upload']) || isset($_POST['upload_video'])) {
    $title = $_POST['title'] ?? null;
    $desc = $_POST['desc'] ?? null;
    $visibility = $_POST['visibility'] ?? "public";
    $tagsRaw = $_POST['tags'] ?? '';

    // visibilty
    $visibility_type = match ($visibility) {
        'private' => UploadVisibilityEnum::Private,
        'unlisted' => UploadVisibilityEnum::Unlisted,
        'public' => UploadVisibilityEnum::Public,
        default => UploadVisibilityEnum::Public,
    };

    // tags
    $tags = $tagsRaw === ''
        ? []
        : preg_split('/[\s,]+/', trim($tagsRaw, ','));

    $file = $_FILES['fileToUpload'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        Utilities::notifyBanner("notify_upload_technical_issue", "/upload");
    }

    try {
        $new = $actions->create(
            $file['tmp_name'],
            $file['name'],
            $title,
            $desc,
            $visibility_type,
            $tags,
            !empty($_POST['rating']),
            $_POST['original_site'] ?? null,
            $sb->getLocalizationClass()->parseDate($_POST['original_time'] ?? '') ?: null
        );
    } catch (Exception $e) {
        if ($sb->isDebug()) {
            die("DEBUG: " . $e->getMessage());
        }

        Utilities::notifyBanner("notify_upload_technical_issue", "/upload");
    }

    if ($new instanceof ActionError) {
        Utilities::notifyBanner($new->value, "/upload");
    }

    if ($actions->isPossiblySuspectedReupload($title, $desc)) {
        Utilities::notifyBanner("notify_upload_success_suspected_reupload", "/view/$new", "warning");
    } else {
        Utilities::notifyBanner("notify_upload_success", "/view/$new", "success");
    }
}

echo $twig->render('upload.twig', [
    'limit' => (Utilities::formatBytes(ini_get('upload_max_filesize'))),
]);
