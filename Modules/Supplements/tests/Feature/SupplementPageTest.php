<?php

use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementEffect;
use Modules\Supplements\Models\SupplementGeneInteraction;
use Modules\Supplements\Models\SupplementMechanism;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Models\SupplementSource;
use Modules\Supplements\Models\SupplementStudy;

beforeEach(function () {
    $this->withoutVite();
});

function researchedSupplement(): Supplement
{
    $supplement = Supplement::factory()->create(['name' => 'Magnesium']);
    $research = SupplementResearch::factory()->create([
        'supplement_id' => $supplement->id,
        'status' => ResearchStatus::Complete,
        'summary' => 'Helps with sleep.',
        'safety_notes' => 'Avoid with kidney disease.',
        'completed_at' => now(),
    ]);
    SupplementSource::factory()->create(['research_id' => $research->id, 'source_key' => 'S1', 'url' => 'https://pubmed.ncbi.nlm.nih.gov/1/', 'title' => 'Source one', 'domain' => 'pubmed.ncbi.nlm.nih.gov']);
    SupplementSource::factory()->create(['research_id' => $research->id, 'source_key' => 'S2', 'url' => 'https://ods.od.nih.gov/x', 'title' => 'Source two', 'domain' => 'ods.od.nih.gov']);
    SupplementSource::factory()->create(['research_id' => $research->id, 'source_key' => 'S3', 'url' => 'https://nccih.nih.gov/y', 'title' => 'Source three', 'domain' => 'nccih.nih.gov']);
    SupplementEffect::factory()->create(['research_id' => $research->id, 'claim' => 'Improves sleep quality', 'source_keys' => ['S1', 'ZZ']]);
    SupplementEffect::factory()->create(['research_id' => $research->id, 'claim' => 'Reduces cramps']);
    SupplementMechanism::factory()->create(['research_id' => $research->id, 'description' => 'Modulates NMDA receptors']);
    SupplementStudy::factory()->create(['research_id' => $research->id, 'title' => 'Sleep RCT', 'pmid' => '4242']);
    SupplementStudy::factory()->create(['research_id' => $research->id, 'title' => 'Cramp trial', 'pmid' => null]);

    return $supplement;
}

it('shows every tab for a researched supplement', function () {
    $supplement = researchedSupplement();
    $url = route('supplements.show', $supplement);

    $this->get($url)->assertOk()
        ->assertSee('Helps with sleep.')
        ->assertSee('Avoid with kidney disease.')
        ->assertSee('not medical advice')
        ->assertSee('Effects (2)')->assertSee('Mechanisms (1)')->assertSee('Studies (2)')->assertSee('Sources (3)');

    $this->get("$url?tab=effects")->assertSee('Improves sleep quality')->assertSee('Moderate')->assertSee('S1');
    $this->get("$url?tab=mechanisms")->assertSee('Modulates NMDA receptors');
    $this->get("$url?tab=studies")->assertSee('Sleep RCT')
        ->assertSee('https://pubmed.ncbi.nlm.nih.gov/4242/', false)->assertSee('Cramp trial');
    $this->get("$url?tab=sources")->assertSee('Source one')->assertSee('ods.od.nih.gov')
        ->assertSee('href="https://nccih.nih.gov/y"', false);
});

it('switches tabs and falls back to overview on an unknown tab', function () {
    $supplement = researchedSupplement();

    Livewire::test('supplements::show', ['supplement' => $supplement->id])
        ->set('tab', 'studies')->assertSee('Sleep RCT')
        ->set('tab', 'bogus')->assertSet('tab', 'overview');
});

it('returns 404 for an unknown supplement', function () {
    $this->get('/supplements/999999')->assertNotFound();
    $this->get('/supplements/abc')->assertNotFound();
});

it('shows an empty state when there is no research', function () {
    $supplement = Supplement::factory()->create();

    $this->get(route('supplements.show', $supplement))->assertOk()->assertSee('No research yet');
});

it('shows status states for in-progress and failed research', function () {
    $active = SupplementResearch::factory()->create(['status' => ResearchStatus::Searching]);
    $failed = SupplementResearch::factory()->create(['status' => ResearchStatus::Failed, 'error' => 'Provider timed out']);

    $this->get(route('supplements.show', $active->supplement_id))->assertSee('Research in progress');
    $this->get(route('supplements.show', $failed->supplement_id))->assertSee('Research failed')->assertSee('Provider timed out');
});

it('escapes model and scraped text and never links unsafe urls', function () {
    $supplement = researchedSupplement();
    $research = $supplement->research;
    SupplementEffect::factory()->create(['research_id' => $research->id, 'claim' => '<script>alert(1)</script>', 'source_keys' => ['S9']]);
    SupplementSource::factory()->create(['research_id' => $research->id, 'source_key' => 'S9', 'url' => 'javascript:alert(1)', 'title' => '<img src=x onerror=alert(1)>', 'domain' => 'evil.test']);
    $url = route('supplements.show', $supplement);

    $this->get("$url?tab=effects")
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('javascript:alert(1)', false);

    $this->get("$url?tab=sources")
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
        ->assertDontSee('<img src=x', false)
        ->assertDontSee('href="javascript:', false);
});

it('renders every view registered for the supplement header slot', function () {
    View::getFinder()->prependLocation(module_path('Supplements', 'tests/Fixtures/views'));
    app(PageActions::class)->register('supplement.header', 'header-action');
    $supplement = researchedSupplement();

    $this->get(route('supplements.show', $supplement))->assertOk()
        ->assertSee('Action for '.$supplement->id.' (complete)', false);
});

it('lists gene interactions with their source markers on the Genetics tab', function () {
    $supplement = researchedSupplement();
    $supplement->research->update(['genetics_checked_at' => now()]);
    SupplementGeneInteraction::factory()->create([
        'supplement_id' => $supplement->id, 'gene' => 'MTHFR', 'rsid' => 'rs1801133', 'allele' => 'T',
        'relation' => GeneRelation::Caution, 'explanation' => '<script>alert(1)</script> may need more folate.', 'source_keys' => ['S1'],
    ]);
    $url = route('supplements.show', $supplement);

    $this->get($url)->assertSee('Genetics (1)');
    $this->get("$url?tab=genetics")
        ->assertSee('MTHFR')->assertSee('rs1801133')->assertSee('Allele T')->assertSee('Caution')
        ->assertSee('href="https://pubmed.ncbi.nlm.nih.gov/1/"', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Re-run research');
});

it('says when checked research found no gene interactions', function () {
    $supplement = researchedSupplement();
    $supplement->research->update(['genetics_checked_at' => now()]);

    $this->get(route('supplements.show', $supplement).'?tab=genetics')
        ->assertSee('No genetic interactions found')->assertDontSee('Re-run research');
});

it('asks to re-run research that predates gene interactions', function () {
    $supplement = researchedSupplement();

    $this->get(route('supplements.show', $supplement).'?tab=genetics')
        ->assertSee('Re-run research to check genetic interactions.');
});
