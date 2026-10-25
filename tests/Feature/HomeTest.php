<?php

use App\Models\User;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

beforeEach(function () {
    $this->withoutVite();
    FakeTextGenerator::install();
    $this->profiles = FakeGeneticProfileReader::install();
    $this->relevance = FakeGeneticRelevanceReader::install();
});

function homeUser(string $name = 'Demo User'): User
{
    $user = User::factory()->create(['name' => $name]);

    test()->profiles->for($user->id, new GeneticProfileData($user->id, [
        new VariantData('rs1', 'MTHFR', 'GT', 'T', 'G', null, null, null, LookupStatus::Seeded, 'manual'),
        new VariantData('rs2', 'MTR', 'AG', 'A', 'G', null, null, null, LookupStatus::Pending, 'manual'),
    ]));
    test()->relevance->top($user->id, [new SupplementMatchesData(7, 'Methylfolate', ResearchStatus::Complete, [
        new GeneticMatchData('MTHFR', 'rs1', 'GT', 1, GeneRelation::Helps, 'x', ['S1'], 'allele', 'G'),
        new GeneticMatchData('MTR', 'rs2', 'AG', 1, GeneRelation::Helps, 'x', ['S1'], 'allele', 'G'),
    ])]);

    return $user;
}

function homeSupplement(): void
{
    $supplement = Supplement::factory()->create(['name' => 'Methylfolate', 'normalized_name' => 'methylfolate', 'slug' => 'methylfolate']);
    SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => ResearchStatus::Complete]);
}

it('greets a signed-in user by first name for each time of day', function (string $time, string $period) {
    $this->travelTo(now()->setTimeFromTimeString($time));

    $this->actingAs(homeUser('Demo User'))->get('/')->assertOk()->assertSee("Good {$period}, Demo");
})->with([
    'morning' => ['08:00', 'morning'],
    'afternoon' => ['14:00', 'afternoon'],
    'evening' => ['20:00', 'evening'],
]);

it('shows a signed-in user the date, supplements and variants, the hero and four stats with captions', function () {
    homeSupplement();
    AiRequest::query()->create([
        'operation' => 'generate', 'provider' => 'openai', 'input_tokens' => 1, 'output_tokens' => 1,
        'latency_ms' => 1, 'estimated_cost_usd' => 0.47, 'status' => 'success',
    ]);

    $this->actingAs(homeUser())->get('/')
        ->assertOk()
        ->assertSee(now()->format('l, F j').' · 1 supplement · 2 variants')
        ->assertSee('2 of your variants relate to Methylfolate')
        ->assertSee('Open Methylfolate')
        ->assertSeeHtml('data-stat="supplements"')
        ->assertSee('Research complete')
        ->assertSeeHtml('data-stat="variants"')
        ->assertSee('1 looked up')
        ->assertSeeHtml('data-stat="gene-matches"')
        ->assertSee('Across 1 supplement')
        ->assertSeeHtml('data-stat="ai-spend"')
        ->assertSee('$0.47')
        ->assertSee('1 call in 24h')
        ->assertDontSee('type="search"', false)
        ->assertDontSee('Search');
});

it('welcomes a guest with the sign-in hero and only the two public stats', function () {
    homeSupplement();
    $other = homeUser();

    $this->get('/')
        ->assertOk()
        ->assertSee('Welcome to '.config('app.name'))
        ->assertSee('Sign in to see how supplements relate to your genes')
        ->assertSeeHtml('data-stat="supplements"')
        ->assertSeeHtml('data-stat="ai-spend"')
        ->assertDontSeeHtml('data-stat="variants"')
        ->assertDontSeeHtml('data-stat="gene-matches"')
        ->assertDontSee('Variants')
        ->assertDontSee('Gene matches')
        ->assertDontSeeHtml('data-genotype')
        ->assertDontSeeHtml('data-allele')
        ->assertDontSee('variants')
        ->assertDontSee('type="search"', false);
});

it('shows a user with no profile the add-results prompt', function () {
    $this->actingAs(User::factory()->create())->get('/')
        ->assertOk()
        ->assertSee('Add your genetic results')
        ->assertSeeHtml('href="'.route('genetics.profile').'"')
        ->assertSee('0 variants');
});

it('shows the same supplement total in the subtitle and the stat, including a supplement with no research', function () {
    homeSupplement();
    Supplement::factory()->create();

    $this->actingAs(homeUser())->get('/')
        ->assertOk()
        ->assertSee('· 2 supplements · 2 variants')
        ->assertSeeInOrder(['data-stat="supplements"', 'Supplements', '2']);
});

it('shows the three sections on Home for a signed-in user', function () {
    homeSupplement();

    $this->actingAs(homeUser())->get('/')
        ->assertOk()
        ->assertSeeHtml('data-section="latest-research"')
        ->assertSeeHtml('data-section="your-genes"')
        ->assertSeeHtml('data-section="your-supplements"')
        ->assertSee('Latest research')
        ->assertSee('Your genes')
        ->assertSee('Your supplements')
        ->assertSee('1 variant is waiting for a lookup');
});

it('shows a guest the sign-in card in place of Your genes and keeps genetics data out of the sections', function () {
    homeSupplement();
    $other = homeUser();

    $this->get('/')
        ->assertOk()
        ->assertSee('Sign in to see your genes')
        ->assertDontSee('Look up now')
        ->assertDontSeeHtml('data-gene-group')
        ->assertDontSeeHtml('data-match-count');
});
