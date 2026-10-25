<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;

function statVariant(string $rsid, LookupStatus $status): VariantData
{
    return new VariantData($rsid, 'MTHFR', 'AG', 'G', 'A', null, null, null, $status, 'manual');
}

it('counts variants and those looked up, treating Pending, LookingUp and Failed as not looked up', function () {
    $user = User::factory()->create();
    FakeGeneticProfileReader::install()->for($user->id, new GeneticProfileData($user->id, [
        statVariant('rs1', LookupStatus::Seeded),
        statVariant('rs2', LookupStatus::Complete),
        statVariant('rs3', LookupStatus::Pending),
        statVariant('rs4', LookupStatus::LookingUp),
        statVariant('rs5', LookupStatus::Failed),
    ]));

    Livewire::actingAs($user)->test('genetics::variant-stat')
        ->assertSee('Variants')
        ->assertSeeText('5')
        ->assertSee('2 looked up');
});

it('renders nothing for a guest even when another user has variants', function () {
    $other = User::factory()->create();
    FakeGeneticProfileReader::install()->for($other->id, new GeneticProfileData($other->id, [statVariant('rs1', LookupStatus::Seeded)]));

    Livewire::test('genetics::variant-stat')->assertDontSee('Variants')->assertDontSee('looked up');
});
