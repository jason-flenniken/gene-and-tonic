<?php

namespace Modules\Shared\Contracts;

use Modules\Shared\Data\LinkedSupplementData;

/**
 * Which supplements each of a user's variants is a gene match for. Lets
 * Genetics show links without depending on Recommendations. The caller passes
 * the authenticated user's id; never take it from request or Livewire input.
 */
interface VariantLinks
{
    /**
     * Keyed by rsID; variants with no links are left out. Never carries genotypes.
     *
     * @return array<string, list<LinkedSupplementData>>
     */
    public function forUser(int $userId): array;
}
