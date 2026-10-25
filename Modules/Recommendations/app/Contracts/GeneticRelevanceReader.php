<?php

namespace Modules\Recommendations\Contracts;

use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Data\SupplementMatchesData;

/**
 * How one user's genetic profile relates to one supplement's gene interactions.
 * Rule-based; never calls an AI model. The caller passes the authenticated
 * user's id; never take it from request or Livewire input.
 */
interface GeneticRelevanceReader
{
    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData;

    /**
     * The supplements with the most gene matches for the user, ranked by match
     * count, then name. Supplements with no matches are left out; a user with
     * no profile gets [].
     *
     * @return list<SupplementMatchesData>
     */
    public function topForUser(int $userId, int $limit = 1): array;
}
