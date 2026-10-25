<?php

namespace Modules\Genetics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;

/** @extends Factory<GeneVariant> */
class GeneVariantFactory extends Factory
{
    protected $model = GeneVariant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $rsid = 'rs'.fake()->unique()->numberBetween(1000, 99999999);

        return [
            'rsid' => $rsid,
            'gene' => 'MTHFR',
            'reference_allele' => 'G',
            'risk_allele' => 'A',
            'category' => 'Methylation',
            'category_group' => VariantGroup::Methylation,
            'affects' => 'Folate metabolism.',
            'genotype_effects' => ['AA' => 'Two risk copies.', 'AG' => 'One risk copy.', 'GG' => 'No risk copies.'],
            'sources' => [['url' => "https://www.ncbi.nlm.nih.gov/snp/{$rsid}", 'title' => "dbSNP {$rsid}", 'domain' => 'www.ncbi.nlm.nih.gov']],
            'status' => LookupStatus::Seeded,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'gene' => null,
            'reference_allele' => null,
            'risk_allele' => null,
            'category' => null,
            'category_group' => null,
            'affects' => null,
            'genotype_effects' => null,
            'sources' => null,
            'status' => LookupStatus::Pending,
        ]);
    }
}
