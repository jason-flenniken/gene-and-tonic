<?php

namespace Modules\Recommendations\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\ResearchStatus;

/**
 * A supplement and the user's gene matches for it. The matches carry the
 * user's genotypes, so render them only to that user.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class SupplementMatchesData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<GeneticMatchData>  $matches
     */
    public function __construct(
        public int $supplementId,
        public string $name,
        public ResearchStatus $status,
        public array $matches,
    ) {}
}
