<?php

namespace Modules\Supplements\Support;

final class SupplementName
{
    /** Trim and collapse internal whitespace. */
    public static function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(self::clean($name));
    }
}
