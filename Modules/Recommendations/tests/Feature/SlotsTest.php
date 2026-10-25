<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->create();
    $this->supplement = Supplement::factory()->create(['name' => 'Methylfolate']);
    SupplementResearch::factory()->for($this->supplement)->create(['status' => ResearchStatus::Complete]);

    $match = new GeneticMatchData('MTHFR', 'rs1801133', 'AG', 1, GeneRelation::cases()[0], 'Reduced enzyme.', [], GeneticMatchData::MATCHED_ON_ALLELE, 'A');
    FakeGeneticRelevanceReader::install()->for($this->user->id, $this->supplement->id, new GeneticRelevanceData($this->supplement->id, true, true, [$match]));
});

it('registers the preview and card slots', function () {
    expect(app(PageActions::class)->for('supplement.preview'))->toContain('recommendations::slots.preview-strip')
        ->and(app(PageActions::class)->for('supplement.card'))->toContain('recommendations::slots.match-count');
});

it('shows the gene strip and match count to the signed-in user', function () {
    $this->actingAs($this->user);

    Livewire::test('supplements::catalog')
        ->assertSee('1 gene match')
        ->assertSee('1 of your variant sits on this pathway')
        ->assertSeeHtml('data-genotype')
        ->assertSee('MTHFR');
});

it('says no gene matches for a signed-in user with a profile and none', function () {
    $other = User::factory()->create();
    FakeGeneticRelevanceReader::install()->for($other->id, $this->supplement->id, new GeneticRelevanceData($this->supplement->id, true, true, []));
    $this->actingAs($other);

    Livewire::test('supplements::catalog')->assertSee('no gene matches')->assertDontSeeHtml('data-genotype');
});

it('shows a guest no genotype markup and no match count', function () {
    $this->get('/supplements?selected='.$this->supplement->id)
        ->assertOk()
        ->assertSee('Methylfolate')
        ->assertDontSee('data-genotype', false)
        ->assertDontSee('data-allele', false)
        ->assertDontSee('data-match-count', false)
        ->assertDontSee('gene match')
        ->assertDontSee('sits on this pathway')
        ->assertDontSee('sit on this pathway');
});
