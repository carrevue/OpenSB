<?php

namespace SquareBracket;

enum UploadFlags: int
{
    /**
     * 00000001: featured upload
     */
    case FLAG_FEATURED = 1;

    /**
     * 00000010: Unprocessed VIDEO upload
     */
    case FLAG_UNPROCESSED = 2;

    /**
     * 00000100: "Block guests from viewing this upload"
     */
    case FLAG_BLOCK_GUESTS = 4;

    /**
     * 00001000: "Block users from commenting in this upload"
     */
    case FLAG_BLOCK_COMMENTS = 8;

    /**
     * 00010000: "upload has custom thumbnail"
     */
    case FLAG_CUSTOM_THUMBNAIL = 16;

    public static function toArray(int $flags): array
    {
        return [
            'featured' => (bool)($flags & self::FLAG_FEATURED->value),
            'unprocessed' => (bool)($flags & self::FLAG_UNPROCESSED->value),
            'block_guests' => (bool)($flags & self::FLAG_BLOCK_GUESTS->value),
            'block_comments' => (bool)($flags & self::FLAG_BLOCK_COMMENTS->value),
            'custom_thumbnail' => (bool)($flags & self::FLAG_CUSTOM_THUMBNAIL->value),
        ];
    }
}