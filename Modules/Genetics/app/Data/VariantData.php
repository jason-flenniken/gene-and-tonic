<?php

namespace Modules\Genetics\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class VariantData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  string  $genotype  Normalized and resolved to the plus strand where the reference alleles allow.
     * @param  string|null  $genotypeEffect  The reference row's effect text for this genotype, if any.
     */
    public function __construct(
        public string $rsid,
        public ?string $gene,
        public string $genotype,
        public ?string $referenceAllele,
        public ?string $riskAllele,
        public ?string $category,
        public ?string $affects,
        public ?string $genotypeEffect,
        public LookupStatus $status,
        public string $source,
        public ?VariantGroup $group = null,
        public ?string $variantName = null,
    ) {}
}
