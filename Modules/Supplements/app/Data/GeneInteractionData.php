<?php

namespace Modules\Supplements\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\GeneRelation;

/**
 * A generic, population-level gene-supplement interaction. Holds no user data.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class GeneInteractionData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<string>  $sourceKeys
     */
    public function __construct(
        public string $gene,
        public ?string $rsid,
        public ?string $allele,
        public GeneRelation $relation,
        public string $explanation,
        public array $sourceKeys,
    ) {}
}
