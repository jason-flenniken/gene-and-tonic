<?php

namespace Modules\Recommendations\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;
use Modules\Supplements\Enums\GeneRelation;

/**
 * One of the user's variants that a supplement's gene interaction applies to.
 * Carries the user's genotype, so render it only to that user.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class GeneticMatchData implements Arrayable
{
    use ArrayableData;

    public const string MATCHED_ON_ALLELE = 'allele';

    public const string MATCHED_ON_RSID = 'rsid';

    public const string MATCHED_ON_GENE = 'gene';

    /**
     * @param  int|null  $copies  Copies (1 or 2) of the interaction's allele; null when the interaction names no usable allele.
     * @param  list<string>  $sourceKeys
     * @param  string  $matchedOn  One of `allele`, `rsid` or `gene`.
     * @param  string|null  $allele  The interaction's effect allele on the plus strand; null unless matched on `allele`.
     * @param  string|null  $variantName  The variant's common name (e.g. "C677T"), when known.
     */
    public function __construct(
        public string $gene,
        public string $rsid,
        public string $genotype,
        public ?int $copies,
        public GeneRelation $relation,
        public string $explanation,
        public array $sourceKeys,
        public string $matchedOn,
        public ?string $allele = null,
        public ?string $variantName = null,
    ) {}
}
