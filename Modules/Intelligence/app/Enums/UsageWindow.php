<?php

namespace Modules\Intelligence\Enums;

use Carbon\CarbonImmutable;

enum UsageWindow: string
{
    case Day = '24h';
    case Week = '7d';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Day => '24 hours',
            self::Week => '7 days',
            self::All => 'All time',
        };
    }

    public function since(): ?CarbonImmutable
    {
        return match ($this) {
            self::Day => CarbonImmutable::now()->subDay(),
            self::Week => CarbonImmutable::now()->subDays(7),
            self::All => null,
        };
    }
}
