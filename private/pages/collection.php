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

namespace OpenSB\Pages;

use OpenSB\CollectionData;
use OpenSB\UploadQuery;
use OpenSB\Utilities;

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

$collection = new CollectionData($database, $id);
$upload_query = new UploadQuery($sb);

$data = $collection->getData();
if (!$data) {
    Utilities::notifyBanner("invalid_collection", "/");
}

$upload_map = array_map('intval', $data['uploads']);
$query = implode(', ', $upload_map);


$uploads = $upload_query->query(
    "v.timestamp DESC",
    100, // TODO: pagination
    sprintf("v.id in (%s)", $query)
);

echo $twig->render('collection.twig', [
    'data' => $data,
    'uploads' => Utilities::makeUploadArray($database, $uploads),
]);