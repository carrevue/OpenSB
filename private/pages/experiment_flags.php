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

global $sb, $twig;

use Core\Utilities;

$flags = [
    "finalium_enable_spf" => [
        "name" => "Structured Page Fragments",
        "description" => "Use SPF. Only works under Finalium."
    ],
    "debug_fulptube_branding" => [
        "name" => "[DEBUG] FulpTube branding override",
        "description" => "Overrides squareBracket branding with FulpTube branding. Only works under Chaziz mode."
    ],
    "exp_wavelet" => [
        "name" => "Enable Wavelet",
        "description" => "Replace journals with social media-styled posts."
    ],
    "exp_wavelet_comments" => [
        "name" => "Use Wavelet for comments",
        "description" => "Use new Wavelet posts as comments rather than the current comments system."
    ],
    "exp_trinium_profile_settings" => [
        "name" => "New Trinium profile settings",
        "description" => "Moves profile settings from account settings to your profile."
    ],
    "exp_trinium_wavelet_profile" => [
        "name" => "New Trinium profile layout",
        "description" => "Experimental redesign meant to coincidence with Wavelet."
    ],
    "trinium_link_coloring" => [
        "name" => "Trinium link coloring",
        "description" => "Leftover of scrapped 2024 Charla functionality."
    ],
    "exp_collections" => [
        "name" => "Collections/Playlists",
        "description" => "Enables collections/playlists. Currently does not do anything."
    ],
    "exp_report" => [
        "name" => "Upload reporting",
        "description" => "Enables ability to report uploads. Currently does not do anything."
    ],
    "exp_inbox" => [
        "name" => "Private messaging",
        "description" => "Enables ability to view and send private messages. Currently does not do anything."
    ],
    "exp_player" => [
        "name" => "Player thumbnails/captions",
        "description" => "Currently does not do anything."
    ],
];

if (isset($_POST['apply'])) {
    $options = [];
    $options = $sb->getOptionsCookie();

    foreach ($flags as $key => $flag) {
        if (isset($_POST[$key])) {
            $options[$key] = true;
        } else {
            if (isset($options[$key])) {
                $options[$key] = false;
            }
        }
    }

    $sb->setOptionCookie($options);

    Utilities::notifyBanner("notify_successfully_updated_options", "/", "success");
}

echo $twig->render('experiment_flags.twig', [
    'flags' => $flags,
]);
