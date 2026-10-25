<?php

use App\Models\User;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\HealthTopic;
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

    $this->get("$url?tab=effects")->assertSee('Improves sleep quality')->assertSee('moderate')->assertSee('S1');
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

it('renders every view registered for the supplement panels slot below the tab panels with only the supplement id', function (string $tab) {
    View::getFinder()->prependLocation(module_path('Supplements', 'tests/Fixtures/views'));
    app(PageActions::class)->register('supplement.panels', 'header-action');
    $supplement = researchedSupplement();

    $this->get(route('supplements.show', $supplement)."?tab=$tab")->assertOk()
        ->assertSeeInOrder(['data-panel="'.$tab.'"', 'Action for '.$supplement->id.' (none)'], false);
})->with(['overview', 'genetics']);

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

it('renders the panels slot on Overview and Genetics only', function (string $tab) {
    View::getFinder()->prependLocation(module_path('Supplements', 'tests/Fixtures/views'));
    app(PageActions::class)->register('supplement.panels', 'header-action');
    $supplement = researchedSupplement();

    $this->get(route('supplements.show', $supplement)."?tab=$tab")->assertOk()
        ->assertDontSee('Action for '.$supplement->id);
})->with(['effects', 'mechanisms', 'studies', 'sources']);

it('replaces the back link with a breadcrumb', function () {
    $supplement = researchedSupplement();

    $this->get(route('supplements.show', $supplement))
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSee('href="'.route('supplements.index').'"', false)
        ->assertDontSee('Back to supplements');
});

it('shows the kicker, topic tags and titled effects, and filters effects by category', function () {
    $supplement = researchedSupplement();
    $research = $supplement->research;
    $research->update(['kicker' => 'B vitamin · active folate', 'topics' => ['methylation', 'heart']]);
    $research->effects()->delete();
    SupplementEffect::factory()->create(['research_id' => $research->id, 'title' => 'Homocysteine levels', 'claim' => 'Lowers homocysteine', 'category' => HealthTopic::Methylation]);
    SupplementEffect::factory()->create(['research_id' => $research->id, 'title' => 'Cardiovascular outcomes', 'claim' => 'May reduce events', 'category' => HealthTopic::Heart]);

    Livewire::test('supplements::show', ['supplement' => $supplement->id])
        ->assertSee('B vitamin · active folate')
        ->assertSeeHtml('data-topics')
        ->assertSee('Methylation')
        ->assertSee('Heart')
        ->assertSee('Homocysteine levels')
        ->assertSee('Cardiovascular outcomes')
        ->assertSeeHtml('data-effect-chips')
        ->set('effectTopic', 'heart')
        ->assertSee('Cardiovascular outcomes')
        ->assertDontSee('Homocysteine levels')
        ->set('tab', 'effects')
        ->assertSee('Cardiovascular outcomes')
        ->assertSee('May reduce events')
        ->assertDontSee('Homocysteine levels')
        ->set('effectTopic', 'bogus')
        ->assertSet('effectTopic', '')
        ->assertSee('Homocysteine levels');
});

it('falls back for legacy research with no kicker, topics or effect titles', function () {
    $supplement = researchedSupplement();

    Livewire::test('supplements::show', ['supplement' => $supplement->id])
        ->assertDontSeeHtml('data-kicker')
        ->assertDontSeeHtml('data-topics')
        ->assertDontSeeHtml('data-effect-chips')
        ->assertSee('Improves sleep quality')
        ->assertSee('Reduces cramps');

    $this->get(route('supplements.show', $supplement).'?tab=effects')->assertOk()
        ->assertSee('Improves sleep quality')->assertSee('Reduces cramps');
});

it('polls and shows step progress only while research is active', function () {
    $active = SupplementResearch::factory()->create(['status' => ResearchStatus::Searching]);

    $this->get(route('supplements.show', $active->supplement_id))
        ->assertSee('wire:poll.3s', false)
        ->assertSee('step 2 of 4');

    $active->update(['status' => ResearchStatus::Complete, 'completed_at' => now()]);

    $this->get(route('supplements.show', $active->supplement_id))
        ->assertDontSee('wire:poll', false)
        ->assertDontSee('step 2 of 4');
});

it('escapes a stored kicker', function () {
    $supplement = researchedSupplement();
    $supplement->research->update(['kicker' => '<script>alert(1)</script>']);

    $this->get(route('supplements.show', $supplement))
        ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('shows a guest no genotype or allele pair for matches that belong to another user', function () {
    $supplement = researchedSupplement();
    $supplement->research->update(['genetics_checked_at' => now()]);
    SupplementGeneInteraction::factory()->create([
        'supplement_id' => $supplement->id, 'gene' => 'MTHFR', 'rsid' => 'rs1801133', 'allele' => 'T',
        'relation' => GeneRelation::Helps, 'explanation' => 'Bypasses the reduced step.', 'source_keys' => [],
    ]);
    $owner = User::factory()->create();
    FakeGeneticProfileReader::install()->for($owner->id, new GeneticProfileData($owner->id, [
        new VariantData('rs1801133', 'MTHFR', 'AG', 'G', 'A', null, null, null, LookupStatus::Seeded, 'manual'),
    ]));
    $url = route('supplements.show', $supplement);

    foreach (['overview', 'genetics'] as $tab) {
        $this->get("$url?tab=$tab")->assertOk()
            ->assertDontSee('data-genotype', false)
            ->assertDontSee('data-allele', false)
            ->assertDontSee('data-variant-gene', false)
            ->assertDontSee('How does Magnesium fit my genes?');
    }

    $this->actingAs($owner)->get("$url?tab=overview")
        ->assertSee('data-genotype', false)
        ->assertSee('data-allele', false)
        ->assertSee('How does Magnesium fit my genes?');
});
