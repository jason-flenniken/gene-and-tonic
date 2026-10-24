<?php

use Modules\Intelligence\Data\SourceDocument;
use Modules\Supplements\Support\ResearchFindingsBuilder;
use Modules\Supplements\Support\UnusableResearchOutput;

function unitSources(): array
{
    return [
        new SourceDocument('S1', 'https://pubmed.ncbi.nlm.nih.gov/12345678/', 'Trial', 'pubmed.ncbi.nlm.nih.gov', null, 'md'),
        new SourceDocument('S2', 'https://ods.od.nih.gov/factsheets/Zinc/', 'Fact sheet', 'ods.od.nih.gov', null, 'md'),
    ];
}

function unitOutput(array $overrides = []): array
{
    return array_merge([
        'summary' => 'Summary',
        'safety_notes' => '',
        'effects' => [],
        'mechanisms' => [],
        'studies' => [],
    ], $overrides);
}

function unitStudy(array $overrides = []): array
{
    return array_merge([
        'title' => 'T', 'design' => 'RCT', 'population' => null, 'sample_size' => 10, 'year' => 2020,
        'finding' => 'F', 'evidence_grade' => 'strong', 'source_id' => 'S1', 'pmid' => null,
    ], $overrides);
}

it('rejects malformed top-level output', function (array $output) {
    ResearchFindingsBuilder::build($output, unitSources());
})->throws(UnusableResearchOutput::class)->with([
    'empty' => [[]],
    'summary not a string' => [unitOutput(['summary' => ['x']])],
    'effects missing' => [['summary' => 's', 'safety_notes' => '', 'mechanisms' => [], 'studies' => []]],
    'studies wrong type' => [unitOutput(['studies' => 'nope'])],
]);

it('de-duplicates valid effect citations and drops uncited effects', function () {
    $findings = ResearchFindingsBuilder::build(unitOutput(['effects' => [
        ['claim' => 'A', 'outcome' => 'positive', 'evidence_grade' => 'Strong', 'source_ids' => ['S1', 'S1', 'S99', 'S2']],
        ['claim' => 'B', 'outcome' => 'positive', 'evidence_grade' => 'strong', 'source_ids' => ['S99']],
        ['claim' => 'C', 'outcome' => 'positive', 'evidence_grade' => 'bogus', 'source_ids' => ['S1']],
        'junk',
    ]]), unitSources());

    expect($findings->effects)->toHaveCount(1)
        ->and($findings->effects[0]['source_keys'])->toBe(['S1', 'S2'])
        ->and($findings->effects[0]['evidence_grade'])->toBe('strong');
});

it('sanitises study numbers and the pmid', function () {
    $findings = ResearchFindingsBuilder::build(unitOutput(['studies' => [
        unitStudy(['year' => 1800, 'sample_size' => -4, 'pmid' => '999']),
        unitStudy(['source_id' => 'S2', 'year' => '2021', 'sample_size' => '120', 'pmid' => '555']),
        unitStudy(['source_id' => 'S2', 'pmid' => 'not-a-number']),
    ]]), unitSources());

    expect($findings->studies[0])->toMatchArray(['year' => null, 'sample_size' => null, 'pmid' => '12345678'])
        ->and($findings->studies[1])->toMatchArray(['year' => 2021, 'sample_size' => 120, 'pmid' => '555'])
        ->and($findings->studies[2]['pmid'])->toBeNull();
});
