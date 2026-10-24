<?php

namespace Modules\Supplements\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\ResearchStatus;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class SupplementResearchData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<EffectData>  $effects
     * @param  list<MechanismData>  $mechanisms
     * @param  list<StudyData>  $studies
     * @param  list<SourceData>  $sources
     */
    public function __construct(
        public int $supplementId,
        public string $name,
        public ResearchStatus $status,
        public ?string $summary,
        public ?string $safetyNotes,
        public array $effects,
        public array $mechanisms,
        public array $studies,
        public array $sources,
        public ?CarbonImmutable $completedAt,
        public ?string $error = null,
    ) {}
}
