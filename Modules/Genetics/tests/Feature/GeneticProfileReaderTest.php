<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

it('returns the user\'s variants joined to reference data', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create([
        'rsid' => 'rs1801133',
        'gene' => 'MTHFR',
        'reference_allele' => 'G',
        'risk_allele' => 'A',
        'category' => 'Methylation',
        'affects' => 'Folate metabolism.',
        'genotype_effects' => ['AA' => 'Two copies.', 'AG' => 'One copy.', 'GG' => 'None.'],
    ]);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs1801133', 'genotype' => 'AG', 'source' => 'genome.txt']);

    $profile = app(GeneticProfileReader::class)->forUser($user->id);

    expect($profile)->toBeInstanceOf(GeneticProfileData::class)
        ->and($profile->userId)->toBe($user->id)
        ->and($profile->count())->toBe(1)
        ->and($profile->isEmpty())->toBeFalse()
        ->and($profile->variants[0])->toEqual(new VariantData(
            rsid: 'rs1801133',
            gene: 'MTHFR',
            genotype: 'AG',
            referenceAllele: 'G',
            riskAllele: 'A',
            category: 'Methylation',
            affects: 'Folate metabolism.',
            genotypeEffect: 'One copy.',
            status: LookupStatus::Seeded,
            source: 'genome.txt',
            group: VariantGroup::Methylation,
        ));
});

it('resolves a genotype stored while its reference row was pending', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create([
        'rsid' => 'rs1801133',
        'reference_allele' => 'G',
        'risk_allele' => 'A',
        'genotype_effects' => ['AA' => 'Two copies.'],
        'status' => LookupStatus::Complete,
    ]);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs1801133', 'genotype' => 'TT']);

    $variant = app(GeneticProfileReader::class)->forUser($user->id)->variants[0];

    expect($variant->genotype)->toBe('AA')
        ->and($variant->genotypeEffect)->toBe('Two copies.');
});

it('has no effect text for a pending row', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->pending()->create(['rsid' => 'rs999']);
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs999', 'genotype' => 'CT']);

    $variant = app(GeneticProfileReader::class)->forUser($user->id)->variants[0];

    expect($variant->status)->toBe(LookupStatus::Pending)
        ->and($variant->genotype)->toBe('CT')
        ->and($variant->genotypeEffect)->toBeNull()
        ->and($variant->gene)->toBeNull();
});

it('never returns another user\'s variants', function () {
    [$a, $b] = User::factory()->count(2)->create();
    GeneVariant::factory()->create(['rsid' => 'rs4680']);
    GeneVariant::factory()->create(['rsid' => 'rs1801133']);
    UserVariant::factory()->create(['user_id' => $a->id, 'rsid' => 'rs4680']);
    UserVariant::factory()->create(['user_id' => $b->id, 'rsid' => 'rs1801133']);
    UserVariant::factory()->create(['user_id' => $b->id, 'rsid' => 'rs4680', 'genotype' => 'GG']);

    $profile = app(GeneticProfileReader::class)->forUser($a->id);

    expect(array_map(fn (VariantData $v): string => $v->rsid, $profile->variants))->toBe(['rs4680'])
        ->and($profile->variants[0]->genotype)->toBe('AG');
});

it('returns an empty profile for a user with no variants', function () {
    $user = User::factory()->create();

    $profile = app(GeneticProfileReader::class)->forUser($user->id);

    expect($profile->isEmpty())->toBeTrue()
        ->and($profile->count())->toBe(0)
        ->and($profile->variants)->toBe([]);
});

it('decrypts once for repeated reads in the same request', function () {
    $user = User::factory()->create();
    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs4680']);
    $reader = app(GeneticProfileReader::class);

    DB::enableQueryLog();
    $first = $reader->forUser($user->id);
    $second = $reader->forUser($user->id);
    $userVariantQueries = collect(DB::getQueryLog())->filter(fn (array $q): bool => str_contains($q['query'], 'user_variants'));
    DB::disableQueryLog();

    expect($second)->toBe($first)
        ->and($userVariantQueries)->toHaveCount(1);
});

it('returns fresh data after a variant write in the same request', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs4680']);
    $variant = UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs4680', 'genotype' => 'AG']);
    $reader = app(GeneticProfileReader::class);

    expect($reader->forUser($user->id)->variants[0]->genotype)->not->toBe('GG');

    $variant->update(['genotype' => 'GG']);
    expect($reader->forUser($user->id)->variants[0]->genotype)->toBe('GG');

    UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => 'rs9999999']);
    expect($reader->forUser($user->id)->count())->toBe(2);

    GeneVariant::query()->where('rsid', 'rs4680')->first()->update(['gene' => 'COMT']);
    expect($reader->forUser($user->id)->variants[0]->gene)->toBe('COMT');

    $variant->delete();
    expect($reader->forUser($user->id)->count())->toBe(1);
});

it('never serves one user\'s cached profile to another user', function () {
    [$a, $b] = User::factory()->count(2)->create();
    UserVariant::factory()->create(['user_id' => $a->id, 'rsid' => 'rs4680']);
    $reader = app(GeneticProfileReader::class);

    $reader->forUser($a->id);
    $profileB = $reader->forUser($b->id);

    expect($profileB->userId)->toBe($b->id)
        ->and($profileB->isEmpty())->toBeTrue()
        ->and($reader->forUser($a->id)->count())->toBe(1);
});
