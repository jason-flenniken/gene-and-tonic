<?php

use Carbon\CarbonImmutable;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementEffect;
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
