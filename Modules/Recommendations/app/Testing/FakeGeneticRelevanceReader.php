<?php

namespace Modules\Recommendations\Testing;

use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticRelevanceData;

/**
 * In-memory GeneticRelevanceReader for other modules' tests.
 */
class FakeGeneticRelevanceReader implements GeneticRelevanceReader
{
    /** @var array<string, GeneticRelevanceData> */
    private array $relevance = [];

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

    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData
    {
        return $this->relevance["$userId:$supplementId"] ?? new GeneticRelevanceData($supplementId, false, false, []);
    }
}
