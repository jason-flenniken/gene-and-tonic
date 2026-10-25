<?php

namespace Modules\Supplements\Testing;

use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Data\SupplementSummaryData;

/**
 * In-memory SupplementResearchReader for other modules' tests.
 */
class FakeSupplementResearchReader implements SupplementResearchReader
{
    /** @var array<int, SupplementResearchData> */
    private array $research = [];

    /** @var array<int, SupplementSummaryData> */
    private array $summaries = [];

    public static function install(): self
    {
        $fake = new self;

        app()->instance(SupplementResearchReader::class, $fake);

        return $fake;
    }

    public function with(SupplementResearchData $research): self
    {
        $this->research[$research->supplementId] = $research;

        return $this;
    }

    public function withSummary(SupplementSummaryData $summary): self
    {
        $this->summaries[$summary->supplementId] = $summary;

        return $this;
    }

    public function forSupplement(int $supplementId): ?SupplementResearchData
    {
        return $this->research[$supplementId] ?? null;
    }

    public function count(): int
    {
        return count($this->summaries);
    }

    /**
     * Explicit summaries first; research added with with() fills in a derived summary for any id without one.
     */
    public function all(): array
    {
        $summaries = $this->summaries;

        foreach ($this->research as $id => $research) {
            $summaries[$id] ??= new SupplementSummaryData(
                supplementId: $id,
                name: $research->name,
                status: $research->status,
                completedAt: $research->completedAt,
                effectsCount: count($research->effects),
                studiesCount: count($research->studies),
                sourcesCount: count($research->sources),
                topics: $research->topics,
                geneInteractions: $research->geneInteractions,
                geneticsChecked: $research->geneticsChecked,
            );
        }

        return array_values($summaries);
    }
}
