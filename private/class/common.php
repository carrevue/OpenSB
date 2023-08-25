<?php

namespace openSB;

// we need this at the top, or else version numbers won't work.
require_once(dirname(__DIR__) . "/class/version.php");

$gitBranch = "opensb-legacy"; // hardcoded because otherwise in some cases we get a warning
$versionNumber = $buildNumber . "-" . $gitBranch;

if (!file_exists(dirname(__DIR__) . '/conf/config.php')) {
    die('<b>A configuration file could not be found. Please read the installing instructions in the README file.</b>');
}

require_once(dirname(__DIR__) . '/conf/config.php');

if ($isDebug and !isset($rawOutputRequired)) {
    // load profiler first
    require_once('profiler.php');
    $profiler = new Profiler();
}

require_once(dirname(__DIR__) . '/../vendor/autoload.php'); //dogshit

// aaaa psr-4 autoload!!! - rgb

// load interfaces first
foreach (glob(dirname(__DIR__) . "/interface/*.php") as $file) {
    require_once($file);
}

// and then the classes
foreach (glob(dirname(__DIR__) . "/class/*.php") as $file) {
    require_once($file);
}

// Holy shit! Classes!
$sql = new MySQL($host, $user, $pass, $db);
if ($isQoboTV) {
    $storage = new BunnyStorage;
} else {
    $storage = new LocalStorage;
}

// user agent blocking shit
if (!empty($blockedUA) && isset($_SERVER['HTTP_USER_AGENT'])) {
    foreach ($blockedUA as $bl) {
        if (str_contains($_SERVER['HTTP_USER_AGENT'], $bl)) {
            http_response_code(403);
            echo '403';
            die();
        }
    }
}

if (isset($_COOKIE['frontend'])) {
    $frontendName = $_COOKIE['frontend'];
    $frontend = $_COOKIE['frontend'] . "-desktop";
    $frontendCommon = $_COOKIE['frontend'] . "-common";
    $mobileFrontend = $_COOKIE['frontend'] . "-mobile";
} else {
    $frontendName = ($useTemplate ?? 'sbnext');
    $frontend = (isset($useTemplate) ? $useTemplate . "-desktop" : 'sbnext-desktop');
    $frontendCommon = (isset($useTemplate) ? $useTemplate . "-common" : 'sbnext-common');
    $mobileFrontend = (isset($useTemplate) ? $useTemplate . "-mobile" : 'sbnext-mobile');
}

// cattleDog's verify.php fucks up if this isn't done.
if (!isset($_SESSION['isCattleDog'])) {
    $lang = new Lang(dirname(__DIR__) . "/lang/" . ($_COOKIE['language'] ?? 'en-US') . ".json");
	$accountfields = "id, name, email, customcolor, title, about, powerlevel, joined, lastview";
    $userfields = Users::userfields();
    $videofields = "v.id, v.video_id, v.title, v.description, v.time, v.post_type, (SELECT COUNT(*) FROM views WHERE video_id = v.video_id) AS views, (SELECT COUNT(*) FROM comments WHERE id = v.video_id) AS comments, (SELECT COUNT(*) FROM favorites WHERE video_id = v.video_id) AS favorites, (SELECT COUNT(*) FROM favorites WHERE video_id = v.video_id) AS favorites, v.videolength, v.category_id, v.author";
}

if (preg_match('~MSIE|Internet Explorer~i', $_SERVER['HTTP_USER_AGENT']) || preg_match('~Trident/7.0(/x*/)?; rv:11.0~',$_SERVER['HTTP_USER_AGENT'])) {
	$browser['legacy_masthead_fix'] = true;
	$browser['legacy_disable_graph'] = true;
	$browser['legacy_disable_videojs'] = true;
	if (preg_match('/MSIE (.*?);/', $_SERVER['HTTP_USER_AGENT'])) {
		$browser['name'] = "Internet Explorer Legacy"; // IE 10 and below
		$browser['codename'] = "ie_old";
	} else {
	$browser['name'] = "Internet Explorer"; //IE 11
	$browser['codename'] = "ie";
	}
} elseif (preg_match('~Goanna~', $_SERVER['HTTP_USER_AGENT'])) {
	$browser['name'] = "Pale Moon/K-Meleon (or derivatives)"; // Pale Moon or K-Meleon (which uses an old build of Goanna but whatever)
	$browser['codename'] = "palemoon";
} elseif (preg_match('~Firefox~', $_SERVER['HTTP_USER_AGENT'])) {
	$browser['name'] = "Firefox (or deriatives)"; // Firefox
	$browser['codename'] = "firefox";
} elseif (preg_match('~Chrome~', $_SERVER['HTTP_USER_AGENT'])) {
	$browser['name'] = "Chromium (or deriatives)"; // Chrome
	$browser['codename'] = "chromium";
} elseif (preg_match('~AppleWebKit~', $_SERVER['HTTP_USER_AGENT'])) {
	$browser['name'] = "Safari"; // Safari
	$browser['codename'] = "webkit";
} elseif (preg_match('~Presto~', $_SERVER['HTTP_USER_AGENT'])) {
	$browser['name'] = "Legacy Opera"; // Presto-era Opera
	$browser['codename'] = "legacy-opera";
	$browser['legacy_masthead_fix'] = true;
	$browser['legacy_disable_graph'] = true;
	$browser['legacy_disable_videojs'] = true;
}

if ($isMaintenance && !isCli()) {
    error(403, "This openSB instance is currently offline.");
} else {
    $ipban = $sql->fetch("SELECT * FROM ipbans WHERE ? LIKE ip", [getUserIpAddr()]);
    if ($ipban) {
        // todo: replace "sorry about that" text on error template with ban reason so we can make a ip ban page consistent with finalium/111 -grkb 8/24/2022
        http_response_code(403);

        printf(
            "<p>Your IP address has been banned.</p>" .
            "<p><strong>Reason:</strong> %s</p>",
            $ipban['reason']);

        die();
    }
}

// Cookie auth
if (isset($_COOKIE['SBTOKEN'])) {
    $id = $sql->result("SELECT id FROM users WHERE token = ?", [$_COOKIE['SBTOKEN']]);

    if ($id) {
        // Valid cookie, logged in
        $log = true;
    } else {
        // Invalid cookie, not logged in
        $log = false;
    }
} else {
    // No cookie, not logged in
    $log = false;
}

// Theme selector stuff
if (isset($_COOKIE['theme'])) {
    $theme = $_COOKIE['theme'];
} else {
    // No cookie, default to the default theme
    $theme = 'default';
}


// Rounded pfp shit (suggested by sks2002)
if (isset($_COOKIE['profilepicture'])) {
    $pfpRoundness = $_COOKIE['profilepicture'];
} else {
    // No cookie, default to circle
    $pfpRoundness = 'default';
}

if ($log) {
    $userdata = $sql->fetch("SELECT $accountfields FROM users WHERE id = ?", [$id]);
    $notificationCount = $sql->result("SELECT COUNT(*) FROM notifications WHERE recipient = ?", [$userdata['id']]);
    $userbandata = $sql->fetch("SELECT * FROM bans WHERE userid = ?", [$id]);
} else {
    $userdata['powerlevel'] = 1;
}

function navigationList() {
	return [
		"browse" => [
			"name" => __("Browse"),
			"icon" => "video",
			"url" => "/browse.php",
			"htmlid" => "browse",
		],
	];
}

$userdata['timezone'] = 'America/New York';
