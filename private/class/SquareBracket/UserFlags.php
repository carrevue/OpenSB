<?php

namespace SquareBracket;

enum UserFlags: int
{
    /**
     * 00000001: Enable profile customization
     */
    case FLAG_PROFILE_CUSTOMIZATION_ENABLED = 1;


    /**
     * 10000000: Account was created through FulpTube.rocks
     */
    case FLAG_FULPTUBE_ACCOUNT = 80;

    public static function toArray(int $flags): array
    {
        return [
            'fulptube_account' => (bool)($flags & self::FLAG_FULPTUBE_ACCOUNT->value),
            'profile_customization_enabled' => (bool)($flags & self::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value),
        ];
    }
}