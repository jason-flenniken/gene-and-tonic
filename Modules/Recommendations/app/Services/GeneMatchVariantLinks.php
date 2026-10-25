<?php

namespace Modules\Recommendations\Services;

use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Data\LinkedSupplementData;

/**
 * A variant links to every supplement it is a gene match for, by the same
 * rules as the supplement page's genetic panel. Supplements are listed in
 * ranking order (most matches first).
 */
final class GeneMatchVariantLinks implements VariantLinks
{
    public function __construct(private GeneticRelevanceReader $relevance) {}

    public function forUser(int $userId): array
    {
        $links = [];

        foreach ($this->relevance->topForUser($userId, PHP_INT_MAX) as $supplement) {
            foreach ($supplement->matches as $match) {
                $links[$match->rsid][$supplement->supplementId] = new LinkedSupplementData($supplement->supplementId, $supplement->name);
            }
        }

        return array_map(array_values(...), $links);
    }
}
