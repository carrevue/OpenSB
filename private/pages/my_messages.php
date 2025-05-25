<?php

namespace OpenSB;

global $twig, $database, $auth, $twig_error, $orange;

use SquareBracket\UserData;
use SquareBracket\Utilities;

if(!$orange->isIncompleteFeaturesEnabled()) {
    http_response_code(404);
    echo $twig_error->render("404.twig", ["page" => "failwhale"]);
}

if (!$auth->isUserLoggedIn())
{
    Utilities::notifyBanner("Please login to continue.", "/login");
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
        "author" => 1,
        "recipient" => 1,
        "date" => time() - 1200,
    ],
    "2" => [
        "id" => 2,
        "reply_to_id" => null,
        "title" => "OpenSB Private Message Test #2",
        "contents" => "This is another private message. Something something lorem ipsum. You should see a small portion 
        of the contents of this private message. Foo!",
        "author" => 1,
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