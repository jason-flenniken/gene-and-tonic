<?php

use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;

function geneticsFakeProfile(int $userId): GeneticProfileData
{
    return new GeneticProfileData($userId, [
        new VariantData(
            rsid: 'rs1801133',
            gene: 'MTHFR',
            genotype: 'AG',
            referenceAllele: 'G',
            riskAllele: 'A',
            category: 'Methylation',
            affects: null,
            genotypeEffect: null,
            status: LookupStatus::Seeded,
            source: 'manual',
        ),
    ]);
}

it('binds itself as the profile reader', function () {
    $fake = FakeGeneticProfileReader::install();

    expect(app(GeneticProfileReader::class))->toBe($fake);
});

it('returns the stored profile for a user', function () {
    $profile = geneticsFakeProfile(3);

    FakeGeneticProfileReader::install()->for(3, $profile)->for(4, geneticsFakeProfile(4));

    expect(app(GeneticProfileReader::class)->forUser(3))->toBe($profile);
});

it('returns an empty profile for an unknown user', function () {
    FakeGeneticProfileReader::install()->for(3, geneticsFakeProfile(3));

    $profile = app(GeneticProfileReader::class)->forUser(99);

    expect($profile->userId)->toBe(99)
        ->and($profile->isEmpty())->toBeTrue();
});
