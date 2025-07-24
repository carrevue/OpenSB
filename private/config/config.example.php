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
    "discord_webhook" => [
        "enabled" => false,
        "url" => "",
    ],
    // put "PROD" for production, put "DEV" for development
    "mode" => "PROD",
    "site" => "squarebracket",
    "maintenance" => false,
    "lockdown" => false,
    "cache" => false,
    "enable_registration" => true,
    "invite_keys" => false,
    "branding" => [
        "name" => "OpenSB Instance",
        "assets" => "/assets/placeholder",
    ],
];
