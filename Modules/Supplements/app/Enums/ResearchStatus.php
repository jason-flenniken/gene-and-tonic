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
