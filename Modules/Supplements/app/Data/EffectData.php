<?php

namespace Modules\Supplements\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\EvidenceGrade;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class EffectData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<string>  $sourceKeys
     */
    public function __construct(
        public string $claim,
        public ?string $outcome,
        public EvidenceGrade $evidenceGrade,
        public array $sourceKeys,
    ) {}
}
