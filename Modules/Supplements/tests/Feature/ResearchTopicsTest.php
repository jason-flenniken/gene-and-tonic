<?php

use Illuminate\Support\Facades\Blade;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Intelligence\Testing\FakeWebResearcher;
use Modules\Supplements\Actions\ResearchSupplement;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementEffect;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Repositories\ResearchRepository;

use function Pest\Laravel\assertDatabaseHas;

function topicsRun(array $output): Supplement
{
    $supplement = Supplement::factory()->create(['name' => 'Methylfolate']);
    SupplementResearch::factory()->for($supplement)->create();

    FakeWebResearcher::install()->returning(pipelineSources());
    FakeTextGenerator::install()->respondWith(pipelineOutput($output));

    (new RunSupplementResearch($supplement->id))->handle(app(ResearchSupplement::class), app(ResearchRepository::class));

    return $supplement;
}

function topicsEffect(array $overrides = []): array
{
    return array_merge(['claim' => 'Lowers homocysteine', 'outcome' => 'positive', 'evidence_grade' => 'strong', 'source_ids' => ['S1'], 'title' => null, 'category' => null], $overrides);
}

it('stores the kicker, topics, effect title and category and the reader returns them', function () {
    $supplement = topicsRun([
        'kicker' => 'B vitamin · active folate',
        'topics' => ['methylation', 'heart'],
        'effects' => [topicsEffect(['title' => 'Homocysteine levels', 'category' => 'heart'])],
    ]);

    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data->kicker)->toBe('B vitamin · active folate')
        ->and($data->topics)->toBe([HealthTopic::Methylation, HealthTopic::Heart])
        ->and($data->effects[0]->title)->toBe('Homocysteine levels')
        ->and($data->effects[0]->category)->toBe(HealthTopic::Heart);
});

it('drops invalid topics, maps invalid categories to Other, caps titles and tolerates a missing kicker', function () {
    $supplement = topicsRun([
        'topics' => ['banana', 'Heart', 'heart', 'mood', 'sleep', 'stress'],
        'effects' => [topicsEffect(['title' => str_repeat('t', 200), 'category' => 'banana'])],
    ]);

    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data->status->value)->toBe('complete')
        ->and($data->kicker)->toBeNull()
        ->and($data->topics)->toBe([HealthTopic::Heart, HealthTopic::Mood, HealthTopic::Sleep])
        ->and($data->effects[0]->category)->toBe(HealthTopic::Other)
        ->and(mb_strlen($data->effects[0]->title))->toBe(80);
});

it('stores a kicker capped at 60 characters', function () {
    $supplement = topicsRun(['kicker' => str_repeat('k', 90)]);

    expect(app(SupplementResearchReader::class)->forSupplement($supplement->id)->kicker)->toHaveLength(60);
});

it('returns nulls and empty values for legacy rows', function () {
    $supplement = Supplement::factory()->create();
    $research = SupplementResearch::factory()->for($supplement)->create(['status' => 'complete', 'summary' => 's']);
    SupplementEffect::factory()->create(['research_id' => $research->id]);
    assertDatabaseHas('supplement_effects', ['research_id' => $research->id, 'title' => null, 'category' => null]);

    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data->kicker)->toBeNull()
        ->and($data->topics)->toBe([])
        ->and($data->effects[0]->title)->toBeNull()
        ->and($data->effects[0]->category)->toBeNull();
});

it('collapses markup and newlines to one line and escapes them on render', function () {
    $supplement = topicsRun([
        'kicker' => "<script>alert(1)</script>\n  B vitamin",
        'effects' => [topicsEffect(['title' => "<script>alert(1)</script>\r\nHomocysteine"])],
    ]);

    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data->kicker)->toBe('<script>alert(1)</script> B vitamin')
        ->and($data->effects[0]->title)->toBe('<script>alert(1)</script> Homocysteine');

    $html = Blade::render('{{ $kicker }}|{{ $title }}', ['kicker' => $data->kicker, 'title' => $data->effects[0]->title]);

    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>');
});
