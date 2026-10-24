<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

it('creates a pending reference row and one user variant for an unknown rsid', function () {
    $user = User::factory()->create();

    $reference = app(AddVariant::class)->handle($user->id, 'rs12345', 'c/t', gene: 'ABC1');

    expect($reference->rsid)->toBe('rs12345')
        ->and($reference->status)->toBe(LookupStatus::Pending)
        ->and($reference->gene)->toBe('ABC1')
        ->and(GeneVariant::count())->toBe(1);

    $variant = UserVariant::sole();

    expect($variant->user_id)->toBe($user->id)
        ->and($variant->rsid)->toBe('rs12345')
        ->and($variant->genotype)->toBe('CT')
        ->and($variant->source)->toBe('manual')
        ->and($variant->genetic_upload_id)->toBeNull();
});

it('updates the same user and rsid so the newest genotype wins', function () {
    $user = User::factory()->create();
    $action = app(AddVariant::class);

    $action->handle($user->id, 'rs12345', 'CT');
    $action->handle($user->id, 'rs12345', 'TT', source: 'genome.txt', uploadId: 7);

    $variant = UserVariant::sole();

    expect($variant->genotype)->toBe('TT')
        ->and($variant->source)->toBe('genome.txt')
        ->and($variant->genetic_upload_id)->toBe(7)
        ->and(GeneVariant::count())->toBe(1);
});

it('stores a gene-strand MTHFR genotype as its plus-strand equivalent', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs1801133', 'reference_allele' => 'G', 'risk_allele' => 'A']);

    $reference = app(AddVariant::class)->handle($user->id, 'rs1801133', 'CT');

    expect($reference->status)->toBe(LookupStatus::Seeded)
        ->and(UserVariant::sole()->genotype)->toBe('AG');
});

it('leaves a known reference row untouched', function () {
    $user = User::factory()->create();
    GeneVariant::factory()->create(['rsid' => 'rs4680', 'gene' => 'COMT']);

    app(AddVariant::class)->handle($user->id, 'rs4680', 'AG', gene: 'OTHER');

    expect(GeneVariant::sole()->gene)->toBe('COMT');
});

it('rejects an invalid rsid or genotype and writes nothing', function (string $rsid, string $genotype) {
    $user = User::factory()->create();

    expect(fn () => app(AddVariant::class)->handle($user->id, $rsid, $genotype))
        ->toThrow(InvalidArgumentException::class);

    expect(GeneVariant::count())->toBe(0)
        ->and(UserVariant::count())->toBe(0);
})->with([
    'malformed rsid' => ['rs12x', 'CT'],
    'genotype outside the alphabet' => ['rs12345', 'XY'],
    'no-call' => ['rs12345', '--'],
]);

it('never puts the genotype in the exception message', function () {
    $user = User::factory()->create();

    try {
        app(AddVariant::class)->handle($user->id, 'rs12345', 'ZQ');
    } catch (InvalidArgumentException $e) {
        expect($e->getMessage())->not->toContain('ZQ');

        return;
    }

    $this->fail('Expected an InvalidArgumentException.');
});

it('rejects an invalid gene symbol', function () {
    $user = User::factory()->create();

    expect(fn () => app(AddVariant::class)->handle($user->id, 'rs12345', 'CT', gene: 'not a gene!'))
        ->toThrow(InvalidArgumentException::class);

    expect(DB::table('gene_variants')->count())->toBe(0);
});

it('caps the source label at 255 characters', function () {
    $user = User::factory()->create();

    app(AddVariant::class)->handle($user->id, 'rs12345', 'CT', source: str_repeat('é', 300).'.txt');

    expect(mb_strlen(UserVariant::sole()->source))->toBe(255);
});
