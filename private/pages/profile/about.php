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

namespace OpenSB\Pages;

global $auth, $database, $twig, $sb;

use OpenSB\Utilities;
use OpenSB\UploadQuery;
$options = $sb->getLocalOptions();

// if we're not on finalium, redirect to the normal profile page.
if ($sb->getLocalOptions()["skin"] != "finalium") {
    Utilities::redirect("/user/$username");
}

include_once('_include.php');

$upload_query = new UploadQuery($sb);

$user_uploads_query_limit = 12;

$user_uploads = $upload_query->query("v.timestamp desc", $user_uploads_query_limit, "v.author = ?", [$data["id"]]);

if ($options["skin"] == "bootstrap") {
    $user_journal_limit = 3;
} else {
    $user_journal_limit = 8;
}

$user_journals =
    $database->fetchArray(
        $database->query("SELECT j.* FROM journals j WHERE
                         j.author = ? 
                         ORDER BY j.timestamp 
                         DESC LIMIT ?", [$data["id"], $user_journal_limit])
    );

$is_own_profile = ($data["id"] == $auth->getUserID());

$followers = $database->result("SELECT COUNT(user) FROM user_follows WHERE id = ?", [$data["id"]]);
$followed = Utilities::isFollowingUser($data["id"]);
$views = $database->result("SELECT SUM(views) FROM uploads WHERE author = ?", [$data["id"]]);

$profile_data = [
    "id" => $data["id"],
    "username" => $data["name"],
    "displayname" => $data["title"],
    "color" => $data["userlink_color"],
    "about" => ($data['about'] ?? false),
    "joined" => $data["joined"],
    "connected" => $data["last_seen"],
    "is_current" => $is_own_profile,
    "followers" => $followers,
    "following" => $followed,
    "is_staff" => ($data["powerlevel"] > 1),
    "views" => $views,
    "customization" => $profile_customization_data?->getData() ?? false,
];

echo $twig->render("profile_about.twig", [
    'data' => $profile_data,
]);
