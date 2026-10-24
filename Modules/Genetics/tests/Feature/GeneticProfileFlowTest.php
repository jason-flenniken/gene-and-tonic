<?php

use App\Models\User;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Database\Seeders\GeneVariantSeeder;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;

it('reads back a gene-strand MTHFR genotype on the plus strand with its seeded effect', function () {
    $this->seed(GeneVariantSeeder::class);
    $user = User::factory()->create();

    app(AddVariant::class)->handle($user->id, 'rs1801133', 'C/T');

    $variant = app(GeneticProfileReader::class)->forUser($user->id)->variants[0];

    expect($variant->rsid)->toBe('rs1801133')
        ->and($variant->gene)->toBe('MTHFR')
        ->and($variant->genotype)->toBe('AG')
        ->and($variant->genotypeEffect)->toBe(GeneVariant::where('rsid', 'rs1801133')->sole()->genotype_effects['AG'])
        ->and($variant->status)->toBe(LookupStatus::Seeded);
});
