<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2026 Chaziz

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

namespace Pages\Dashboard;

global $auth, $twig, $database, $sb;

use Data\Upload\UploadData;
use Data\Upload\UploadFlags;
use Core\Utilities;
use Data\User\UserRoleEnum;

include_once('_include.php');

function handle_views(array $views): array {
    foreach ($views as &$view) {
        $user = $view['user'] ?? '';
        $timestamp = $view['timestamp'] ?? 0;

        if ($user === 'PokTubeUser') { // sb specific behavior (poktube views)
            $view['user'] = 'Pre-April 24th 2021 View';
            $view['timestamp'] = 0;
        } elseif ($timestamp === 1662664200) { // sb specific behavior (classic sb views)
            $view['user'] = 'Pre-September 8th 2022 View';
            $view['timestamp'] = 0;
        } elseif ($timestamp === 1709269200) { // sb specific behavior (qobo views)
            $view['user'] = 'Pre-March 1st 2024 View';
            $view['timestamp'] = 0;
        } elseif (filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) { // stripped ipv4
            $parts = explode('.', $user);
            $view['user'] = "{$parts[0]}.{$parts[1]}.x.x";
        } elseif (filter_var($user, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) { // stripped ipv6
            $groups = explode(':', $user);
            $view['user'] = implode(':', array_slice($groups, 0, 2)) . ':x:x:x:x:x:x';
        } elseif (preg_match('/[a-zA-Z]/', $user)) { // pre-opensb 2.0 views
            $view['user'] = 'Pre-July 21st 2025 View';
        }
    }
    unset($view);

    return $views;
}

$upload = new UploadData($database, $id);
$data = $upload->getData();

$views = $database->fetchArray($database->query("SELECT * FROM upload_views WHERE upload_id = ? ORDER BY timestamp", [$id]));

echo $twig->render("upload_views.twig", [
    'upload' => $data,
    'views' => handle_views($views),
]);