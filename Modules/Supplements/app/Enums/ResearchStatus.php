<?php

namespace Modules\Supplements\Enums;

enum ResearchStatus: string
{
    case Queued = 'queued';
    case Searching = 'searching';
    case Analyzing = 'analyzing';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return match ($this) {
            self::Queued, self::Searching, self::Analyzing => true,
            self::Complete, self::Failed => false,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Status tone: neutral, active, ok or bad. */
    public function tone(): string
    {
        return match ($this) {
            self::Queued => 'neutral',
            self::Searching, self::Analyzing => 'active',
            self::Complete => 'ok',
            self::Failed => 'bad',
        };
    }

    /** Progress step out of 4 for an active run, null once finished. */
    public function step(): ?int
    {
        return match ($this) {
            self::Queued => 1,
            self::Searching => 2,
            self::Analyzing => 3,
            self::Complete, self::Failed => null,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'zinc',
            self::Searching => 'blue',
            self::Analyzing => 'amber',
            self::Complete => 'green',
            self::Failed => 'red',
        };
    }
}
