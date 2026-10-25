<?php

namespace Modules\Recommendations\Testing;

use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Data\SupplementMatchesData;

/**
 * In-memory GeneticRelevanceReader for other modules' tests.
 */
class FakeGeneticRelevanceReader implements GeneticRelevanceReader
{
    /** @var array<string, GeneticRelevanceData> */
    private array $relevance = [];

    /** @var array<int, list<SupplementMatchesData>> */
    private array $top = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(GeneticRelevanceReader::class, $fake);

        return $fake;
    }

    public function for(int $userId, int $supplementId, GeneticRelevanceData $data): self
    {
        $this->relevance["$userId:$supplementId"] = $data;

        return $this;
    }

    /**
     * @param  list<SupplementMatchesData>  $supplements  Ranked and filtered like the real reader on the way out.
     */
    public function top(int $userId, array $supplements): self
    {
        $this->top[$userId] = $supplements;

        return $this;
    }

    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData
    {
        return $this->relevance["$userId:$supplementId"] ?? new GeneticRelevanceData($supplementId, false, false, []);
    }

    public function topForUser(int $userId, int $limit = 1): array
    {
        $top = array_values(array_filter($this->top[$userId] ?? [], fn (SupplementMatchesData $supplement): bool => $supplement->matches !== []));

        usort($top, fn (SupplementMatchesData $a, SupplementMatchesData $b): int => count($b->matches) <=> count($a->matches) ?: strcasecmp($a->name, $b->name));

        return array_slice($top, 0, max(0, $limit));
    }
}
