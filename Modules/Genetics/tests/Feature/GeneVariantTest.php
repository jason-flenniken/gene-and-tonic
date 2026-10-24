<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;

it('round-trips a reference row with its casts', function () {
    GeneVariant::create([
        'rsid' => 'rs1801133',
        'gene' => 'MTHFR',
        'reference_allele' => 'G',
        'risk_allele' => 'A',
        'category' => 'Methylation',
        'affects' => 'Folate metabolism.',
        'genotype_effects' => ['AA' => 'Reduced activity.', 'AG' => 'Mildly reduced.', 'GG' => 'Typical.'],
        'sources' => [['url' => 'https://www.ncbi.nlm.nih.gov/snp/rs1801133', 'title' => 'dbSNP rs1801133', 'domain' => 'www.ncbi.nlm.nih.gov']],
        'status' => LookupStatus::Seeded,
    ]);

    $row = GeneVariant::where('rsid', 'rs1801133')->sole();

    expect($row->status)->toBe(LookupStatus::Seeded)
        ->and($row->genotype_effects)->toBe(['AA' => 'Reduced activity.', 'AG' => 'Mildly reduced.', 'GG' => 'Typical.'])
        ->and($row->sources[0]['domain'])->toBe('www.ncbi.nlm.nih.gov')
        ->and($row->looked_up_at)->toBeNull();
});

it('rejects a duplicate rsid', function () {
    GeneVariant::create(['rsid' => 'rs4680', 'status' => LookupStatus::Pending]);
    GeneVariant::create(['rsid' => 'rs4680', 'status' => LookupStatus::Pending]);
})->throws(QueryException::class);

it('has no user column', function () {
    expect(Schema::hasColumn('gene_variants', 'user_id'))->toBeFalse();
});
