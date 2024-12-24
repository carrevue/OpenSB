<?php

return [
    // Database details. (OpenSB only supports MySQL / MariaDB databases)
    "mysql" => [
        "database" => "sb",
        "username" => "root",
        "password" => "",
        "host" => "127.0.0.1",
    ],
    "captcha" => [
        "enabled" => false,
        "secret" => "",
        "public" => ""
    ],
    // put "PROD" for production, put "DEV" for development
    "mode" => "PROD",
    "site" => "squarebracket",
    "maintenance" => false,
    "cache" => false,
    "invite_keys" => false,
    "branding" => [
        "name" => "OpenSB Instance",
        "slogan" => "Insert slogan here",
        "assets" => "/assets/placeholder",
    ],
];
