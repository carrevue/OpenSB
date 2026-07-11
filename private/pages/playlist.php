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

namespace Pages;

use Data\Playlist\Playlist;
use Data\Upload\UploadQuery;
use Core\Utilities;

global $sb, $database, $twig;

if (Utilities::isClassicSkin()) {
    if (isset($_GET["list"])) { // get "list" parameter and set that as the id
        $id = $_GET["list"];
    } //elseif (isset($id)) { // if id is already defined, this is likely a trinium-style url, so redirect to playlist
    //    Utilities::redirect('/playlist?list=' . $id, 301);
    //}
} else {
    if (isset($_GET["list"])) { // handle awkward edgecase
        Utilities::redirect('/collection/' . $_GET['list'], 301);
    }
}

$playlist = new Playlist($sb, $id);

$data = $playlist->getData();
if (!$data) {
    Utilities::notifyBanner("invalid_playlist", "/");
}

$uploads = $playlist->getUploads(100);

echo $twig->render('playlist.twig', [
    'data' => $data,
    'uploads' => $uploads,
]);