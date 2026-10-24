<?php

namespace Modules\Genetics\Support;

/**
 * A validated lookup answer, ready to store on a gene_variants row.
 */
final readonly class LookupResult
{
    /**
     * @param  array<string, string>  $genotypeEffects  keyed by normalized plus-strand genotype
     * @param  list<array{url: string, title: string, domain: string}>  $sources
     */
    public function __construct(
        public ?string $gene,
        public string $category,
        public string $affects,
        public ?string $referenceAllele,
        public ?string $riskAllele,
        public array $genotypeEffects,
        public array $sources,
    ) {}
}
