<?php

namespace SquareBracket;

use InvalidArgumentException;

enum UploadRatingEnum: int
{
    case General = 0;
    case Questionable = 1; // unused
    case Mature = 2;

    public static function fromString(string $rating): self
    {
        return match(strtolower($rating)) {
            'general' => self::General,
            'questionable' => self::Questionable,
            'mature' => self::Mature,
            default => throw new InvalidArgumentException("invalid rating $rating")
        };
    }

    // for some reason the db uses sql enums and i forgot why i did it like this.
    public function toString(): string
    {
        return match($this) {
            self::General => 'general',
            self::Questionable => 'questionable',
            self::Mature => 'mature',
        };
    }
}
