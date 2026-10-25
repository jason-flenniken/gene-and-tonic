<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;

function summary(int $id, string $name, int $effects, int $studies, ?string $completed, array $topics = [], ResearchStatus $status = ResearchStatus::Complete): SupplementSummaryData
{
    return new SupplementSummaryData($id, $name, $status, $completed === null ? null : now()->parse($completed)->toImmutable(), $effects, $studies, 0, $topics);
}

beforeEach(fn () => $this->travelTo(now()->setDate(2026, 10, 25)->setTime(12, 0)));

it('lists the four most recent completions with topics or the Complete meta, newest first', function () {
    FakeSupplementResearchReader::install()
        ->withSummary(summary(1, 'Oldest', 1, 1, '2026-10-01'))
        ->withSummary(summary(2, 'Methylfolate', 4, 3, '2026-10-25 09:00', [HealthTopic::Methylation, HealthTopic::Heart]))
        ->withSummary(summary(3, 'Magnesium', 3, 2, '2026-10-24', []))
        ->withSummary(summary(4, 'Iron', 0, 0, '2026-10-23'))
        ->withSummary(summary(5, 'Tyrosine', 2, 1, '2026-10-22', [HealthTopic::Focus]))
        ->withSummary(summary(6, 'Running', 0, 0, null, [], ResearchStatus::Searching));

    Livewire::test('supplements::latest-research')
        ->assertSeeInOrder(['Methylation · Heart', 'Methylfolate: 4 effects across 3 studies', 'Complete · 1 day ago', 'Magnesium: 3 effects across 2 studies', 'Complete · 2 days ago', 'Iron: no effects were graded', 'Focus', 'Tyrosine: 2 effects across 1 study'])
        ->assertDontSee('Oldest')
        ->assertDontSee('Running')
        ->assertDontSee('0 effects');
});

it('shows the add-a-supplement prompt linking to /supplements when none exist', function () {
    FakeSupplementResearchReader::install();

    Livewire::test('supplements::your-supplements')
        ->assertSeeHtml('data-empty="supplements"')
        ->assertSee('No supplements yet.')
        ->assertSeeHtml('href="'.route('supplements.index').'"');
});

it('derives or looks up the abbreviation tile for each card', function () {
    FakeSupplementResearchReader::install()
        ->withSummary(summary(1, 'Methylfolate', 4, 3, '2026-10-25 09:00'))
        ->withSummary(summary(2, 'Iron', 0, 0, '2026-10-25 08:00'))
        ->withSummary(summary(3, 'Ashwagandha', 2, 2, '2026-10-24'))
        ->withSummary(summary(4, 'Coenzyme Q10', 1, 1, '2026-10-24'));

    Livewire::test('supplements::your-supplements')
        ->assertSeeHtmlInOrder(['MTHF', 'Methylfolate'])
        ->assertSee('Fe')
        ->assertSee('Ashw')
        ->assertSeeHtml('>CQ</span>')
        ->assertSee('4 effects')
        ->assertSee('0 effects')
        ->assertSee('Researched 3 hours ago');
});

it('renders the gene-match count through the slot for a signed-in user and not for a guest', function () {
    FakeSupplementResearchReader::install()->withSummary(summary(7, 'Methylfolate', 4, 3, '2026-10-25 09:00'));
    $user = User::factory()->create();
    FakeGeneticProfileReader::install()->for($user->id, new GeneticProfileData($user->id, [
        new VariantData('rs1', 'MTHFR', 'GT', 'T', 'G', null, null, null, LookupStatus::Seeded, 'manual'),
    ]));
    FakeGeneticRelevanceReader::install()->for($user->id, 7, new GeneticRelevanceData(7, true, true, [
        new GeneticMatchData('MTHFR', 'rs1', 'GT', 1, GeneRelation::Helps, 'x', ['S1'], 'allele', 'G'),
    ]));

    Livewire::actingAs($user)->test('supplements::your-supplements')->assertSeeHtml('data-match-count')->assertSee('1 gene match');
});

it('leaves the gene-match count out for a guest even when a user has matches', function () {
    FakeSupplementResearchReader::install()->withSummary(summary(7, 'Methylfolate', 4, 3, '2026-10-25 09:00'));
    FakeGeneticRelevanceReader::install()->for(0, 7, new GeneticRelevanceData(7, true, true, [
        new GeneticMatchData('MTHFR', 'rs1', 'GT', 1, GeneRelation::Helps, 'x', ['S1'], 'allele', 'G'),
    ]));

    Livewire::test('supplements::your-supplements')->assertDontSeeHtml('data-match-count')->assertDontSee('gene match');
});
