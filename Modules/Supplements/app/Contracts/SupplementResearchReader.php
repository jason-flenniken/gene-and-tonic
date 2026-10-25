<?php

namespace Modules\Supplements\Contracts;

use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Data\SupplementSummaryData;

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

    /**
     * Every supplement that has a research row, ordered by name, with counts,
     * topics and gene interactions. Does not load the research bodies.
     *
     * @return list<SupplementSummaryData>
     */
    public function all(): array;

    /**
     * Every supplement in the catalog, with or without a research row.
     */
    public function count(): int;
}
