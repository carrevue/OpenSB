<?php

/*
  OpenSB: The Open SquareBracket Software

  Copyright (C) 2025 Chaziz

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

global $twig, $database, $auth, $sb;

use Data\User\UserData;
use Core\Utilities;

if (!$auth->isUserLoggedIn()) {
    Utilities::notifyBanner("notify_login_required", "/login");
}

// fetch data from private_messages table

//  `id` int NOT NULL,
//  `reply_to_id` int NULL,
//  `title` varchar(128) NOT NULL,
//  `contents` text NOT NULL,
//  `author` int NOT NULL,
//  `recipient` int NOT NULL,
//  `date` int NOT NULL

// think of this as data from the database.
$database_data = [
    "1" => [
        "id" => 1,
        "reply_to_id" => null,
        "title" => "OpenSB Private Message Test",
        "contents" => "This is a private message. You should see a small portion of the contents of this private 
        message.",
        "author" => -1000,
        "recipient" => 1,
        "date" => time() - 1200,
    ],
    "2" => [
        "id" => 2,
        "reply_to_id" => null,
        "title" => "OpenSB Private Message Test #2",
        "contents" => "This is another private message. Something something lorem ipsum. You should see a small portion 
        of the contents of this private message. Foo!",
        "author" => -1000,
        "recipient" => 1,
        "date" => time() - 700,
    ],
];

// then turn that shit into more usable data?

$messageArray = [];

foreach ($database_data as $message) {
    $userData = new UserData($database, $message["author"]);

    $messageArray[] =
        [
            "id" => $message["id"],
            "title" => $message["title"],
            "contents" => $message["contents"],
            "published" => $message["date"],
            "author" => [
                "id" => $message["author"],
                "info" => $userData->getUserArray(),
            ],
        ];
}

echo $twig->render('my_messages.twig', [
    'data' => $messageArray,
]);
