<?php

namespace openSB;
/**
 * Twig loader, initializes Twig with standard configurations and extensions.
 *
 * @param string $subfolder Subdirectory to use in the templates/ directory.
 * @return Environment Twig object.
 */

use RelativeTime\RelativeTime;
use Twig\Environment;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;

function twigloader($subfolder = '', $customloader = null, $customenv = null)
{
    global $sql, $userfields, $paginationLimit, $tplCache, $tplNoCache, $log, $userdata, $theme, $pfpRoundness,
           $languages, $frontend, $frontendCommon, $mobileFrontend, $notificationCount, $isMaintenance,
           $versionNumber, $isDebug, $userbandata, $browser, $branding, $isQoboTV, $googleAdsClient;
    $detect = new \Detection\MobileDetect;

    if ($log) {
        $totalSubscribers = $sql->result("SELECT SUM(user) FROM subscriptions WHERE user = ?", [$userdata['id']]);
    } else {
        $totalSubscribers = 0;
    }

    $doCache = ($tplNoCache ? false : $tplCache);
    //ugly hack to prevent reading templates from the wrong place
    chdir(__DIR__);
    chdir('../');
    if (!isset($customloader)) {
        /* if ($frontend == "sbnext-desktop") { //finalium is not mobile first.
            if ($detect->isMobile()) {
                $loader = new FilesystemLoader(['templates/' . $mobileFrontend . '/' . $subfolder, 'templates/' . $frontendCommon . '/' . $subfolder]);
            } else {
                $loader = new FilesystemLoader(['templates/' . $frontend . '/' . $subfolder, 'templates/' . $frontendCommon . '/' . $subfolder]);
            }
        } else { //i don't know */
            $loader = new FilesystemLoader(['templates/' . $frontendCommon . '/' . $subfolder]);
        /* } */
    } else {
        $loader = $customloader();
    }

    if (!isset($customenv)) {
        $twig = new Environment($loader, [
            'cache' => $doCache,
			'debug' => $isDebug,
        ]);
    } else {
        $twig = $customenv($loader, $doCache, $isDebug);
    }

    // why was this line of code not added
    $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
        public function load($class)
        {
            if (MarkdownRuntime::class === $class) {
                return new MarkdownRuntime(new DefaultMarkdown());
            }
        }
    });

    $twig->addExtension(new sBTwigExtension());
    $twig->addExtension(new MarkdownExtension());
	if ($isDebug) { 
		$twig->addExtension(new DebugExtension()); 
	} else {
		$twig->addFunction(new TwigFunction('dump', function() {
			return null;
		}));
	}

    $twig->addGlobal('log', $log);
    $twig->addGlobal('userdata', $userdata);
    $twig->addGlobal('theme', $theme);
    $twig->addGlobal('pfpRoundness', $pfpRoundness);
    $twig->addGlobal('glob_languages', $languages);
    $twig->addGlobal('glob_lpp', $paginationLimit);
    $twig->addGlobal('notification_count', $notificationCount);
    $twig->addGlobal('totalSubscribers', $totalSubscribers);
    $twig->addGlobal('version', $versionNumber);
    $twig->addGlobal('isMaintenance', $isMaintenance);
    $twig->addGlobal('isDebug', $isDebug);
    $twig->addGlobal('userbandata', $userbandata);
	$twig->addGlobal('navigationList', navigationList());
	$twig->addGlobal('user_agent', $_SERVER['HTTP_USER_AGENT']);
	$twig->addGlobal('browser_info', $browser);
	$twig->addGlobal('website_branding', $branding);
    $twig->addGlobal('bunnyEnabled', $isQoboTV);
    $twig->addGlobal('ad_client', $googleAdsClient);

    $twig->addGlobal("page_url", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    $twig->addGlobal("domain", (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/");

    return $twig;
}

function jsonDecode($str)
{
    return json_decode($str);
}

function error($errorCode, $message)
{
    $twig = twigloader();
    echo $twig->render('_error.twig', ['err_message' => $message, 'err_code' => $errorCode]);
    die();
}

function comment($comment)
{
    $twig = twigloader('components');
    return $twig->render('comment.twig', ['data' => $comment]);
}

function profileImage($username)
{
    global $isQoboTV, $bunnySettings, $storage;
    $location = '/dynamic/pfp/' . $username . '.png';

    $file_exists = $storage->fileExists('..' . $location);
    if ($isQoboTV) {
        $data = "https://" . $bunnySettings["pullZone"] . $location;
    } else {
        $data = $username;
    }
    $twig = twigloader('components');
    return $twig->render('profileimage.twig', ['data' => $data, 'file_exists' => $file_exists, 'isBanned' => Users::getIsUserBannedFromName($username)]);
}

function videoThumbnail($videodata)
{
    global $isQoboTV, $storage;
    if ($isQoboTV) {
        $data = $storage->getVideoThumbnail($videodata);
        $file_exists = true;
    } else {
        $data = $videodata;
        $file_exists = $storage->getVideoThumbnail($data);
    }
    $twig = twigloader('components');
    return $twig->render('videothumbnail.twig', ['data' => $data, 'file_exists' => $file_exists]);
}

function imageThumbnail($videodata)
{
    global $isQoboTV, $storage;
    if ($isQoboTV) {
        $data = $storage->getImageThumbnail($videodata);
        $file_exists = true;
    } else {
        $data = $videodata;
        $file_exists = $storage->getImageThumbnail($data);
    }
    $twig = twigloader('components');
    return $twig->render('videothumbnail.twig', ['data' => $data, 'file_exists' => $file_exists]);
}

function videoLength($videodata)
{
    $twig = twigloader('components');
    return $twig->render('videolength.twig', ['data' => $videodata]);
}

function browseVideoBox($videodata)
{
    $twig = twigloader('components');
    return $twig->render('browsevideobox.twig', ['data' => $videodata]);
}

function smallVideoBox($videodata)
{
    $twig = twigloader('components');
    return $twig->render('smallvideobox.twig', ['data' => $videodata]);
}

function verticalVideoBox($videodata)
{
    $twig = twigloader('components');
    return $twig->render('verticalvideobox.twig', ['data' => $videodata]);
}

function browseChannelBox($videodata)
{
    $twig = twigloader('components');
    return $twig->render('browsechannelbox.twig', ['data' => $videodata]);
}

function videoBox($videodata)
{
    $twig = twigloader('components');
    return $twig->render('videobox.twig', ['data' => $videodata]);
}

function icon($icon, $size)
{
    $twig = twigloader('components');
    return $twig->render('icon.twig', ['icon' => $icon, 'size' => $size]);
}

function pagination($levels, $lpp, $url, $current)
{
    $twig = twigloader('components');
    return $twig->render('pagination.twig', ['levels' => $levels, 'lpp' => $lpp, 'url' => $url, 'current' => $current]);
}

function relativeTime($time)
{
    $config = [
        'language' => __('\RelativeTime\Languages\English'),
        'separator' => ', ',
        'suffix' => true,
        'truncate' => 1,
    ];

    $relativeTime = new RelativeTime($config);

    return $relativeTime->timeAgo($time);
}

function convertBytes($value, $decimals = 0)
{
    if (is_numeric($value)) {
        return $value;
    } else {
        $value_length = strlen($value);
        $qty = substr($value, 0, $value_length - 1);
        $unit = strtolower(substr($value, $value_length - 1));
        switch ($unit) {
            case 'k':
                $qty *= 1024;
                break;
            case 'm':
                $qty *= 1048576;
                break;
            case 'g':
                $qty *= 1073741824;
                break;
        }
    }
    $sz = 'BKMGTP';
    $factor = floor((strlen($qty) - 1) / 3);
    return sprintf("%.{$decimals}f", $qty / pow(1024, $factor)) . @$sz[$factor];
}
