<?php

use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Shared\Data\LinkedSupplementData;
use Modules\Shared\Testing\FakeVariantLinks;

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

function groupedVariants(VariantGroup $group, int $count, string $prefix, LookupStatus $status = LookupStatus::Seeded): array
{
    return array_map(
        fn (int $i): VariantData => new VariantData("{$prefix}{$i}", 'GENE', 'AG', 'G', 'A', null, null, null, $status, 'manual', $group),
        range(1, $count),
    );
}

it('lists the top five groups by count with the linked tally on the right row', function () {
    $user = User::factory()->create();
    FakeGeneticProfileReader::install()->for($user->id, new GeneticProfileData($user->id, [
        ...groupedVariants(VariantGroup::Methylation, 5, 'rsm'),
        ...groupedVariants(VariantGroup::BrainMood, 12, 'rsb'),
        ...groupedVariants(VariantGroup::Hormones, 8, 'rsh'),
        ...groupedVariants(VariantGroup::HeartLipids, 3, 'rsl'),
        ...groupedVariants(VariantGroup::SleepCircadian, 2, 'rss'),
        ...groupedVariants(VariantGroup::DetoxDrugResponse, 1, 'rsd'),
    ]));
    $link = [new LinkedSupplementData(1, 'Methylfolate')];
    FakeVariantLinks::install()->for($user->id, ['rsm1' => $link, 'rsm2' => $link, 'rsm3' => $link]);

    Livewire::actingAs($user)->test('genetics::your-genes')
        ->assertSeeInOrder(['Brain & mood', '12 variants', 'Hormones', '8 variants', 'Methylation', '5 variants · 3 linked', 'Heart & lipids', '3 variants', 'Sleep & circadian', '2 variants'])
        ->assertDontSee('Detox & drug response')
        ->assertDontSee('1 linked')
        ->assertDontSeeHtml('data-lookup-banner')
        ->assertSeeHtml('href="'.route('genetics.profile', ['group' => 'brain_mood']).'"');
});

it('shows the waiting banner with the count of variants not yet looked up', function () {
    $user = User::factory()->create();
    FakeGeneticProfileReader::install()->for($user->id, new GeneticProfileData($user->id, [
        ...groupedVariants(VariantGroup::Methylation, 2, 'rsm'),
        ...groupedVariants(VariantGroup::Other, 3, 'rsp', LookupStatus::Pending),
        ...groupedVariants(VariantGroup::Other, 1, 'rsf', LookupStatus::Failed),
    ]));
    FakeVariantLinks::install();

    Livewire::actingAs($user)->test('genetics::your-genes')
        ->assertSee('4 variants are waiting for a lookup')
        ->assertSee('Look up now');
});

it('queues only the signed-in user\'s own rows from lookUpAll on Home', function () {
    Queue::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach (['rs1', 'rs2'] as $rsid) {
        GeneVariant::factory()->pending()->create(['rsid' => $rsid]);
        UserVariant::factory()->create(['user_id' => $user->id, 'rsid' => $rsid, 'genotype' => 'AG']);
    }
    GeneVariant::factory()->pending()->create(['rsid' => 'rs9']);
    UserVariant::factory()->create(['user_id' => $other->id, 'rsid' => 'rs9', 'genotype' => 'AG']);

    Livewire::actingAs($user)->test('genetics::your-genes')->call('lookUpAll')
        ->assertDispatched('toast-show', fn ($event, $params) => str_contains(json_encode($params), '2 variants queued for lookup.'));

    Queue::assertPushed(LookupGeneVariant::class, 2);
    Queue::assertNotPushed(LookupGeneVariant::class, fn (LookupGeneVariant $job): bool => $job->rsid === 'rs9');
});

it('shows a guest the sign-in card with no groups, banner or Look up now, even when user 0 has variants', function () {
    FakeGeneticProfileReader::install()->for(0, new GeneticProfileData(0, [
        ...groupedVariants(VariantGroup::Methylation, 2, 'rsm'),
        ...groupedVariants(VariantGroup::Other, 3, 'rsp', LookupStatus::Pending),
    ]));
    FakeVariantLinks::install()->for(0, ['rsm1' => [new LinkedSupplementData(1, 'Methylfolate')]]);

    Livewire::test('genetics::your-genes')
        ->assertSee('Sign in to see your genes')
        ->assertDontSee('Methylation')
        ->assertDontSee('linked')
        ->assertDontSee('waiting for a lookup')
        ->assertDontSee('Look up now');
});

it('rejects a guest calling lookUpAll', function () {
    Livewire::test('genetics::your-genes')->call('lookUpAll')->assertUnauthorized();
});
