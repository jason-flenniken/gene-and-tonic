<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;

const PANEL_SUPPLEMENT = 21;

beforeEach(function () {
    $this->withoutVite();
    $this->text = FakeTextGenerator::install();
    $this->profiles = FakeGeneticProfileReader::install();
    $this->research = FakeSupplementResearchReader::install();
    panelResearch();
});

afterEach(function () {
    expect($this->text->requests())->toBe([]);
});

function panelResearch(bool $geneticsChecked = true, string $explanation = 'Methylfolate bypasses the reduced MTHFR step.'): void
{
    test()->research->with(new SupplementResearchData(
        supplementId: PANEL_SUPPLEMENT,
        name: 'Methylfolate',
        status: ResearchStatus::Complete,
        summary: null,
        safetyNotes: null,
        effects: [],
        mechanisms: [],
        studies: [],
        sources: [],
        completedAt: CarbonImmutable::now(),
        geneInteractions: $geneticsChecked
            ? [new GeneInteractionData('MTHFR', 'rs1801133', 'T', GeneRelation::Helps, $explanation, ['S1'])]
            : [],
        geneticsChecked: $geneticsChecked,
    ));
}

function panelUserWith(string $rsid, string $genotype, ?string $variantName = 'C677T'): User
{
    $user = User::factory()->create();

    test()->profiles->for($user->id, new GeneticProfileData($user->id, [
        new VariantData($rsid, 'MTHFR', $genotype, 'G', 'A', null, null, null, LookupStatus::Seeded, 'manual', null, $variantName),
    ]));

    return $user;
}

it('registers the panel in the supplement panels slot', function () {
    expect(app(PageActions::class)->for('supplement.panels'))->toContain('recommendations::slots.genetic-panel');
});

it('lists a signed-in user\'s match with gene, rsID, genotype, copies, relation and explanation', function () {
    $this->actingAs(panelUserWith('rs1801133', 'AG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('Your genes')
        ->assertSee('MTHFR')
        ->assertSee('rs1801133')
        ->assertSeeHtml('<span data-genotype>AG</span>')
        ->assertSee('1 COPY')
        ->assertSee('Helps')
        ->assertSee('Methylfolate bypasses the reduced MTHFR step.')
        ->assertSee('Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.');
});

it('shows the ring count, variant name, allele pair and relation badge for the owner', function () {
    $this->actingAs(panelUserWith('rs1801133', 'AG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('1 of your variants relates to this supplement')
        ->assertSee('rs1801133')
        ->assertSee('C677T')
        ->assertSeeHtml('data-variant-gene="MTHFR"')
        ->assertSeeHtml('aria-label="Genotype A G; one copy of effect allele A"')
        ->assertSee('Helps');
});

it('falls back to the rsID alone when the variant has no name', function () {
    $this->actingAs(panelUserWith('rs1801133', 'AG', null));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('rs1801133')
        ->assertDontSee('C677T')
        ->assertDontSeeHtml('rs1801133 &middot;');
});

it('says two copies for a homozygous genotype', function () {
    $this->actingAs(panelUserWith('rs1801133', 'AA'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('2 COPIES')
        ->assertDontSee('1 COPY');
});

it('says when none of the user\'s variants match', function () {
    $this->actingAs(panelUserWith('rs1801133', 'GG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('No known interactions with your tested variants.')
        ->assertDontSee('copy')
        ->assertDontSeeHtml('data-genotype');
});

it('links a user with no SNPs to My genetics', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('My genetics')
        ->assertSeeHtml('href="'.route('genetics.profile').'"')
        ->assertDontSee('No known interactions');
});

it('asks to re-run research that predates gene interactions', function () {
    panelResearch(geneticsChecked: false);
    $this->actingAs(panelUserWith('rs1801133', 'AG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('Re-run research to check genetic interactions.')
        ->assertDontSee('No known interactions');
});

it('shows a guest only the sign-in prompt and no genetic data', function () {
    panelUserWith('rs1801133', 'AG');

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSee('Sign in to see how this relates to your genetics.')
        ->assertSeeHtml('href="'.route('login').'"')
        ->assertDontSee('rs1801133')
        ->assertDontSee('copy')
        ->assertDontSee('Methylfolate bypasses')
        ->assertDontSeeHtml('data-genotype')
        ->assertDontSeeHtml('data-allele')
        ->assertDontSeeHtml('data-variant-gene');
});

it('shows each signed-in user only their own matches', function () {
    panelUserWith('rs1801133', 'AG');
    $userB = panelUserWith('rs1801133', 'AA');

    $this->actingAs($userB);

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSeeHtml('<span data-genotype>AA</span>')
        ->assertSee('2 COPIES')
        ->assertDontSeeHtml('<span data-genotype>AG</span>')
        ->assertDontSee('1 COPY');
});

it('rejects a supplement id set from the client', function () {
    $this->actingAs(panelUserWith('rs1801133', 'AG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->set('supplementId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

it('escapes the explanation', function () {
    panelResearch(explanation: '<script>alert(1)</script> helps.');
    $this->actingAs(panelUserWith('rs1801133', 'AG'));

    Livewire::test('recommendations::genetic-panel', ['supplementId' => PANEL_SUPPLEMENT])
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt; helps.')
        ->assertDontSeeHtml('<script>alert(1)</script>');
});

it('embeds the panel from the slot view with only the supplement id', function () {
    $this->blade('@include($view, ["supplementId" => $id])', ['view' => 'recommendations::slots.genetic-panel', 'id' => PANEL_SUPPLEMENT])
        ->assertSee('Your genes')
        ->assertSee('Sign in to see how this relates to your genetics.');
});
