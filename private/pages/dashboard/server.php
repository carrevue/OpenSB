<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025-2026 Chaziz

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

global $auth, $twig, $database, $sb, $path;

use Core\Utilities;
use Data\User\UserRoleEnum;
use Composer\ComposerInstalled;

if (!$auth->userHasRole(UserRoleEnum::Moderator)) {
    Utilities::notifyBanner("notify_no_permission", "/");
}

if (!$auth->hasUserAuthenticatedAsStaff()) {
    Utilities::notifyBanner("notify_dashboard_login_required", "/dashboard/login");
}

if ($sb->getCurrentSkinName() != "trinium") {
    Utilities::notifyBanner("notify_skin_switch_required", "/theme", "accent", ["Trinium"]);
}



function getComposerPackages(): array
{
    $dependencies = [];
    $installed = new ComposerInstalled(SB_VENDOR_PATH . '/composer/installed.json');
    $dependencies += $installed->getInstalledDependencies();
    ksort($dependencies);
    return $dependencies;
}

function get_folder_size($path)
{
    $path = escapeshellarg($path);
    $command = "du -sb $path | cut -f1";
    $size = shell_exec($command);
    return (int)$size;
}


$cpu_name = "Unknown";

$is_windows = str_starts_with(php_uname(), "Windows") ?? false;

// get distro info if on a unix-based system that supports os-release
// this is better than using lsb-release because lsb is some dead linux-only standard while os-release will work on
// anything that uses systemd, including freebsd from what ive seen online. openrc may support this but im not sure.
// -chaziz 12/22/2024
if (file_exists('/etc/os-release')) {
    $os_release = file('/etc/os-release', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $os_data = [];
    foreach ($os_release as $line) {
        list($key, $value) = explode("=", $line, 2);
        $os_data[$key] = trim($value, '"');
    }

    $os_name = $os_data['PRETTY_NAME'] ?? null;
} else {
    $os_name = null;
}

// we dont really support windows hosts, because most people on windows would just attempt hosting opensb using xampp as
// a basis which hasnt been updated since november 2023 and is a big pile of shit. also, theres no reliably fast method
// of getting the uptime of a windows system through php without relying on systemfo which is slow as shit or possibly
// fucking around with winmgmts through the unholy com php class. i didnt even know it was possible to interface with
// windows' ole api via php, what the fuck??? -chaziz 4/15/2025
if (!$is_windows) {
    if (is_readable('/proc/cpuinfo')) {
        foreach (file('/proc/cpuinfo') as $line) {
            if (stripos($line, 'model name') === 0) {
                $cpu_name = trim(explode(':', $line, 2)[1]);
                break;
            }

            if (stripos($line, 'hardware') === 0) {
                $cpu_name = trim(explode(':', $line, 2)[1]);
                break;
            }

            // we have no clue, so fallback into showing the system's arch type
            if (stripos($line, 'processor') === 0) {
                $cpu_name = php_uname('m');
            }
        }
    }

    if (is_readable('/proc/meminfo')) {
        $meminfo = [];

        foreach (file('/proc/meminfo') as $line) {
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $meminfo[$key] = (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
        }

        $total = $meminfo['MemTotal'] * 1024;
        $available = $meminfo['MemAvailable'] * 1024;
        $used = $total - $available;

        $memory = [
            "total" => Utilities::formatBytes($total),
            "used" => Utilities::formatBytes($used),
            "free" => Utilities::formatBytes($available),
            "percentage" => Utilities::calculatePercentage($used, $total),
        ];
    }

    $uptime = shell_exec('uptime -p'); // posix_times() is unreliable
    if ($uptime) {
        $uptime = ltrim($uptime, "up ");
    }

    $avg = sys_getloadavg();

    $root = '/';
    $disk_total = disk_total_space($root);
    $disk_free = disk_free_space($root);
    $disk_used = $disk_total - $disk_free;
    $disk_percentage = Utilities::calculatePercentage($disk_used, $disk_total);

    $instance_size = get_folder_size(SB_ROOT_PATH);

    $disk = [
        "total" => Utilities::formatBytes($disk_total, 2),
        "free" => Utilities::formatBytes($disk_free, 2),
        "used" => Utilities::formatBytes($disk_used, 2),
        "percentage" => $disk_percentage,
        "instance_size" => Utilities::formatBytes($instance_size),
    ];
} else {
    // maybe look into wmic in the future but not now -chaziz 2/6/2026
    $memory = [];
    $uptime = "Unknown";
    $avg = [];
    $disk = [];
}

echo $twig->render("dashboard_server.twig", [
    "packages" => getComposerPackages(),
    "system" => [
        "uname" => php_uname(),
        "os_name" => $os_name,
        "cpu" => $cpu_name,
        "memory" => $memory,
        "uptime" => $uptime,
        "avg" => $avg,
        "is_windows" => $is_windows,
        "disk" => $disk,
    ],
]);
