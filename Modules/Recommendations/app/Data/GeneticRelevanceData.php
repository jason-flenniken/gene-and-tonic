<?php

namespace Modules\Recommendations\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class GeneticRelevanceData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  bool  $hasProfile  False when the user has no SNPs on file.
     * @param  bool  $geneticsChecked  False when the supplement's research predates gene interactions or does not exist.
     * @param  list<GeneticMatchData>  $matches
     */
    public function __construct(
        public int $supplementId,
        public bool $hasProfile,
        public bool $geneticsChecked,
        public array $matches,
    ) {}

    public function hasMatches(): bool
    {
        return $this->matches !== [];
    }
}
