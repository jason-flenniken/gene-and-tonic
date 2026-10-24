<?php

namespace Modules\Supplements\Contracts;

use Modules\Supplements\Data\SupplementResearchData;

interface SupplementResearchReader
{
    /**
     * Null when the supplement does not exist or has no research row yet.
     */
    public function forSupplement(int $supplementId): ?SupplementResearchData;
}
