<?php

namespace Modules\Genetics\Enums;

/**
 * Where a genetic_uploads row is in its processing lifecycle.
 */
enum UploadStatus: string
{
    case Queued = 'queued';
    case Parsing = 'parsing';
    case LookingUp = 'looking_up';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Parsing, self::LookingUp], true);
    }

    /**
     * Status tone for the UI: active, ok, bad or neutral.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Queued, self::Parsing, self::LookingUp => 'active',
            self::Complete => 'ok',
            self::Failed => 'bad',
        };
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
