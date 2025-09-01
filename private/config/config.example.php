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
    "ip_lookup" => [
        "enabled" => false,
        "mmdb" => '', // place this in the config folder
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
        "is_vector" => false,
        "use_wordmark" => false,
    ],
];
