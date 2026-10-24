<?php

namespace Modules\Recommendations\Contracts;

use Modules\Recommendations\Data\GeneticRelevanceData;

/**
 * How one user's genetic profile relates to one supplement's gene interactions.
 * Rule-based; never calls an AI model. The caller passes the authenticated
 * user's id; never take it from request or Livewire input.
 */
interface GeneticRelevanceReader
{
    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData;
}
