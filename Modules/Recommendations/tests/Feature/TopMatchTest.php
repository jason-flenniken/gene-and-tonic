<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;

beforeEach(function () {
    $this->withoutVite();
    $this->profiles = FakeGeneticProfileReader::install();
    $this->relevance = FakeGeneticRelevanceReader::install();
});

function topMatch(string $gene, string $genotype = 'GT', string $allele = 'G'): GeneticMatchData
{
    return new GeneticMatchData($gene, 'rs'.crc32($gene), $genotype, 1, GeneRelation::Helps, 'Explained.', ['S1'], 'allele', $allele);
}

function topUser(int $variants = 3, array $supplements = []): User
{
    $user = User::factory()->create();
    test()->profiles->for($user->id, new GeneticProfileData($user->id, array_map(
        fn (int $i) => new VariantData("rs{$i}", 'MTHFR', 'GT', 'T', 'G', null, null, null, LookupStatus::Seeded, 'manual'),
        range(1, $variants),
    )));
    test()->relevance->top($user->id, $supplements);

    return $user;
}

function methylfolate(ResearchStatus $status = ResearchStatus::Complete): SupplementMatchesData
{
    return new SupplementMatchesData(7, 'Methylfolate', $status, [topMatch('MTHFR'), topMatch('MTR', 'AG'), topMatch('MTRR', 'AG')]);
}

it('shows the top supplement, its match count, up to three gene rows and an open link', function () {
    $user = topUser(supplements: [new SupplementMatchesData(8, 'Iron', ResearchStatus::Complete, [topMatch('HFE')]), methylfolate()]);

    Livewire::actingAs($user)->test('recommendations::top-match')
        ->assertSee('3 of your variants relate to Methylfolate')
        ->assertSee('Open Methylfolate')
        ->assertSeeHtml('href="'.route('supplements.show', 7).'"')
        ->assertSeeHtml('data-variant-gene="MTHFR"')
        ->assertSeeHtml('data-variant-gene="MTR"')
        ->assertSeeHtml('data-variant-gene="MTRR"')
        ->assertSeeHtml('<span data-genotype>GT</span>')
        ->assertSeeHtml('data-effect')
        ->assertDontSee('HFE')
        ->assertDontSee('Add your genetic results');
});

it('caps the gene rows at three', function () {
    $supplement = new SupplementMatchesData(7, 'Methylfolate', ResearchStatus::Complete, [topMatch('A1'), topMatch('A2'), topMatch('A3'), topMatch('A4')]);
    $user = topUser(supplements: [$supplement]);

    $html = Livewire::actingAs($user)->test('recommendations::top-match')->assertSee('4 of your variants relate to Methylfolate')->html();

    expect(substr_count($html, 'data-variant-gene='))->toBe(3);
});

it('offers the PDF download only when the research is complete', function () {
    $complete = Livewire::actingAs(topUser(supplements: [methylfolate()]))->test('recommendations::top-match')->assertSee('Download PDF');
    $running = Livewire::actingAs(topUser(supplements: [methylfolate(ResearchStatus::Searching)]))->test('recommendations::top-match')->assertDontSee('Download PDF');

    expect($complete->html())->toContain('/reports/');
});

it('uses the singular for one matching variant', function () {
    $user = topUser(supplements: [new SupplementMatchesData(7, 'Methylfolate', ResearchStatus::Complete, [topMatch('MTHFR')])]);

    Livewire::actingAs($user)->test('recommendations::top-match')->assertSee('1 of your variants relates to Methylfolate');
});

it('asks a user with no profile to add results, linking to /genetics', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('recommendations::top-match')
        ->assertSee('Add your genetic results')
        ->assertSeeHtml('href="'.route('genetics.profile').'"')
        ->assertDontSee('GENE MATCH');

    expect(route('genetics.profile', absolute: false))->toBe('/genetics');
});

it('hides the hero when the profile has no matches', function () {
    $user = topUser(supplements: []);

    Livewire::actingAs($user)->test('recommendations::top-match')
        ->assertDontSee('GENE MATCH')
        ->assertDontSee('Add your genetic results')
        ->assertDontSee('Sign in')
        ->assertDontSeeHtml('data-variant-gene');
});

it('shows a guest only the sign-in prompt even when another user has matches', function () {
    $other = topUser(supplements: [methylfolate()]);
    $this->relevance->top(0, [methylfolate()]);

    Livewire::test('recommendations::top-match')
        ->assertSee('Sign in to see how supplements relate to your genes')
        ->assertDontSee('Methylfolate')
        ->assertDontSeeHtml('data-genotype')
        ->assertDontSeeHtml('data-allele')
        ->assertDontSeeHtml('data-variant-gene');
});

it('shows the gene match total across supplements and how many supplements', function () {
    $user = topUser(supplements: [methylfolate(), new SupplementMatchesData(8, 'Iron', ResearchStatus::Complete, [topMatch('HFE')])]);

    Livewire::actingAs($user)->test('recommendations::match-stat')
        ->assertSee('Gene matches')
        ->assertSeeText('4')
        ->assertSee('Across 2 supplements');
});

it('says supplement in the singular for one', function () {
    $user = topUser(supplements: [methylfolate()]);

    Livewire::actingAs($user)->test('recommendations::match-stat')->assertSeeText('3')->assertSee('Across 1 supplement');
});

it('renders no gene match stat for a guest', function () {
    topUser(supplements: [methylfolate()]);

    Livewire::test('recommendations::match-stat')->assertDontSee('Gene matches')->assertDontSee('Across');
});
