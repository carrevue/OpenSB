<?php

namespace SquareBracket;

enum UserFlags: int
{
    /**
     * 00000001: Enable profile customization
     */
    case FLAG_PROFILE_CUSTOMIZATION_ENABLED = 1;

    public static function toArray(int $flags): array
    {
        return [
            'profile_customization_enabled' => (bool)($flags & self::FLAG_PROFILE_CUSTOMIZATION_ENABLED->value),
        ];
    }
}