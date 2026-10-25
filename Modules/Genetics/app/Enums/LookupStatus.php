<?php

namespace Modules\Genetics\Enums;

/**
 * Where a gene_variants reference row is in its research lifecycle.
 */
enum LookupStatus: string
{
    case Seeded = 'seeded';
    case Pending = 'pending';
    case LookingUp = 'looking_up';
    case Complete = 'complete';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::LookingUp;
    }

    /**
     * True once a lookup has finished or the row was seeded: every status except Pending, LookingUp and Failed.
     */
    public function isLookedUp(): bool
    {
        return ! in_array($this, [self::Pending, self::LookingUp, self::Failed], true);
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }
}
