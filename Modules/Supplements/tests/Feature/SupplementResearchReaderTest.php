<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\EvidenceGrade;
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

it('returns the DTO graph for a researched supplement', function () {
    $supplement = Supplement::factory()->create(['name' => 'Magnesium']);
    $research = SupplementResearch::factory()->create([
        'supplement_id' => $supplement->id,
        'status' => ResearchStatus::Complete,
        'summary' => 'Sum',
        'safety_notes' => 'Safe-ish',
        'completed_at' => now(),
    ]);
    SupplementEffect::factory()->count(2)->create(['research_id' => $research->id]);
    SupplementMechanism::factory()->create(['research_id' => $research->id]);
    SupplementStudy::factory()->count(2)->create(['research_id' => $research->id, 'pmid' => '123']);
    SupplementSource::factory()->count(3)->create(['research_id' => $research->id]);

    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data)->toBeInstanceOf(SupplementResearchData::class)
        ->and($data->name)->toBe('Magnesium')
        ->and($data->status)->toBe(ResearchStatus::Complete)
        ->and($data->summary)->toBe('Sum')
        ->and($data->effects)->toHaveCount(2)
        ->and($data->effects[0]->evidenceGrade)->toBe(EvidenceGrade::Moderate)
        ->and($data->mechanisms)->toHaveCount(1)
        ->and($data->studies)->toHaveCount(2)
        ->and($data->studies[0]->pubmedUrl())->toBe('https://pubmed.ncbi.nlm.nih.gov/123/')
        ->and($data->sources)->toHaveCount(3)
        ->and($data->completedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($data->toArray()['status'])->toBe('complete');
});

it('returns null for an unknown supplement or one without research', function () {
    $bare = Supplement::factory()->create();

    expect(app(SupplementResearchReader::class)->forSupplement(999999))->toBeNull()
        ->and(app(SupplementResearchReader::class)->forSupplement($bare->id))->toBeNull();
});

it('only builds links for http(s) urls and numeric pmids', function () {
    $source = fn (?string $url) => new SourceData('S1', $url, 't', 'd');

    expect($source('javascript:alert(1)')->safeUrl())->toBeNull()
        ->and($source(null)->safeUrl())->toBeNull()
        ->and($source('https://example.com/a')->safeUrl())->toBe('https://example.com/a');

    $study = fn (?string $pmid) => new StudyData('t', null, null, null, null, null, null, null, $pmid);

    expect($study('12a')->pubmedUrl())->toBeNull()
        ->and($study('1234567890')->pubmedUrl())->toBeNull()
        ->and($study(null)->pubmedUrl())->toBeNull();
});

it('returns stored gene interactions in position order and flags unchecked research', function () {
    $supplement = Supplement::factory()->create();
    $research = SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => ResearchStatus::Complete]);
    SupplementGeneInteraction::factory()->create(['supplement_id' => $supplement->id, 'gene' => 'COMT', 'rsid' => null, 'allele' => null, 'relation' => GeneRelation::Related, 'position' => 1]);
    SupplementGeneInteraction::factory()->create(['supplement_id' => $supplement->id, 'gene' => 'MTHFR', 'position' => 0]);

    $before = app(SupplementResearchReader::class)->forSupplement($supplement->id);
    expect($before->geneticsChecked)->toBeFalse();

    $research->update(['genetics_checked_at' => now()]);
    $data = app(SupplementResearchReader::class)->forSupplement($supplement->id);

    expect($data->geneticsChecked)->toBeTrue()
        ->and(array_map(fn ($g) => $g->gene, $data->geneInteractions))->toBe(['MTHFR', 'COMT'])
        ->and($data->geneInteractions[0]->relation)->toBe(GeneRelation::Helps)
        ->and($data->geneInteractions[0]->sourceKeys)->toBe(['S1'])
        ->and($data->toArray()['geneInteractions'][1]['relation'])->toBe('related');
});

it('lists summaries with counts, topics and gene interactions in a fixed number of queries', function () {
    foreach (['Zinc', 'Folate'] as $name) {
        $supplement = Supplement::factory()->create(['name' => $name]);
        $research = SupplementResearch::factory()->create([
            'supplement_id' => $supplement->id,
            'status' => ResearchStatus::Complete,
            'topics' => ['methylation', 'bogus'],
            'completed_at' => now(),
        ]);
        SupplementEffect::factory()->count(2)->create(['research_id' => $research->id]);
        SupplementStudy::factory()->count(3)->create(['research_id' => $research->id]);
        SupplementSource::factory()->count(4)->create(['research_id' => $research->id]);
        SupplementGeneInteraction::factory()->create(['supplement_id' => $supplement->id]);
    }
    Supplement::factory()->create(['name' => 'Bare']);

    DB::enableQueryLog();
    $all = app(SupplementResearchReader::class)->all();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($all)->toHaveCount(2)
        ->and($all[0]->name)->toBe('Folate')
        ->and($all[0]->effectsCount)->toBe(2)
        ->and($all[0]->studiesCount)->toBe(3)
        ->and($all[0]->sourcesCount)->toBe(4)
        ->and($all[0]->topics)->toBe([HealthTopic::Methylation])
        ->and($all[0]->geneInteractions)->toHaveCount(1)
        ->and($all[0]->completedAt)->toBeInstanceOf(CarbonImmutable::class)
        ->and($queries)->toBe(3);
});
