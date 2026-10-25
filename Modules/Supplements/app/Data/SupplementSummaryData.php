<?php

namespace Modules\Supplements\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Enums\ResearchStatus;

/**
 * A supplement's listing row: status, counts, topics and gene interactions.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SupplementSummaryData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<HealthTopic>  $topics
     * @param  list<GeneInteractionData>  $geneInteractions
     */
    public function __construct(
        public int $supplementId,
        public string $name,
        public ResearchStatus $status,
        public ?CarbonImmutable $completedAt,
        public int $effectsCount,
        public int $studiesCount,
        public int $sourcesCount,
        public array $topics = [],
        public array $geneInteractions = [],
    ) {}
}
