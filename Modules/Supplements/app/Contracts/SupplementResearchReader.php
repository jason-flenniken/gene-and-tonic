<?php

namespace Modules\Supplements\Contracts;

use Modules\Supplements\Data\SupplementResearchData;

/**
 * Read-only access to a supplement's research as a DTO. The `error` field is a
 * failure message only for Failed runs; it is safe to show in the app UI and is
 * not rendered in reports.
 */
interface SupplementResearchReader
{
    /**
     * Null when the supplement does not exist or has no research row yet.
     */
    public function forSupplement(int $supplementId): ?SupplementResearchData;
}
