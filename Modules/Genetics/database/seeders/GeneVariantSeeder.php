<?php

namespace Modules\Genetics\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;

/**
 * Loads the curated reference SNPs. Safe to run repeatedly: rows are matched on rsid.
 */
class GeneVariantSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{rsid: string, gene: string, reference_allele: string, risk_allele: string, category: string, category_group: string, affects: string, genotype_effects: array<string, string>}> $variants */
        $variants = require module_path('Genetics', 'database/data/seed_variants.php');

        foreach ($variants as $variant) {
            // updateOrCreate rather than upsert() so the array and enum casts apply.
            GeneVariant::updateOrCreate(['rsid' => $variant['rsid']], [
                ...$variant,
                'sources' => [[
                    'url' => "https://www.ncbi.nlm.nih.gov/snp/{$variant['rsid']}",
                    'title' => "dbSNP {$variant['rsid']}",
                    'domain' => 'www.ncbi.nlm.nih.gov',
                ]],
                'status' => LookupStatus::Seeded,
                'error' => null,
            ]);
        }
    }
}
