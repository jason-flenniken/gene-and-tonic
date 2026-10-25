<?php

use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;

it('is idempotent', function () {
    $this->seed(GeneVariantSeeder::class);
    $count = GeneVariant::count();

    $this->seed(GeneVariantSeeder::class);

    expect(GeneVariant::count())->toBe($count)
        ->and($count)->toBeGreaterThanOrEqual(28)->toBeLessThanOrEqual(32);
});

it('seeds well-formed plus-strand rows with a dbSNP source', function () {
    $this->seed(GeneVariantSeeder::class);

    GeneVariant::all()->each(function (GeneVariant $row): void {
        expect($row->rsid)->toMatch('/^rs\d+$/')
            ->and($row->gene)->toMatch('/^[A-Z0-9-]{1,15}$/')
            ->and($row->reference_allele)->toBeIn(['A', 'C', 'G', 'T'])
            ->and($row->risk_allele)->toBeIn(['A', 'C', 'G', 'T'])
            ->and($row->risk_allele)->not->toBe($row->reference_allele)
            ->and($row->status)->toBe(LookupStatus::Seeded)
            ->and($row->category)->not->toBeEmpty()
            ->and($row->category_group)->toBeInstanceOf(VariantGroup::class)
            ->and($row->affects)->not->toBeEmpty()
            ->and($row->sources)->toContain([
                'url' => "https://www.ncbi.nlm.nih.gov/snp/{$row->rsid}",
                'title' => "dbSNP {$row->rsid}",
                'domain' => 'www.ncbi.nlm.nih.gov',
            ]);

        $pair = [$row->reference_allele, $row->risk_allele];
        sort($pair);
        $expectedKeys = [$pair[0].$pair[0], $pair[0].$pair[1], $pair[1].$pair[1]];

        expect(array_keys($row->genotype_effects ?? []))->toEqualCanonicalizing($expectedKeys);
    });
});

it('includes the methylfolate demo SNPs', function () {
    $this->seed(GeneVariantSeeder::class);

    expect(GeneVariant::pluck('gene', 'rsid')->all())->toMatchArray([
        'rs1801133' => 'MTHFR',
        'rs1801131' => 'MTHFR',
        'rs1805087' => 'MTR',
        'rs1801394' => 'MTRR',
        'rs601338' => 'FUT2',
    ]);
});

it('records MTHFR C677T on the plus strand', function () {
    $this->seed(GeneVariantSeeder::class);

    $row = GeneVariant::where('rsid', 'rs1801133')->sole();

    expect($row->reference_allele)->toBe('G')
        ->and($row->risk_allele)->toBe('A');
});

it('restores a seeded row that a user add created as pending', function () {
    GeneVariant::factory()->pending()->create(['rsid' => 'rs4680']);

    $this->seed(GeneVariantSeeder::class);

    $row = GeneVariant::where('rsid', 'rs4680')->sole();

    expect($row->status)->toBe(LookupStatus::Seeded)
        ->and($row->gene)->toBe('COMT');
});

it('files MTHFR C677T under Methylation', function () {
    $this->seed(GeneVariantSeeder::class);

    expect(GeneVariant::where('rsid', 'rs1801133')->sole()->category_group)->toBe(VariantGroup::Methylation);
});
