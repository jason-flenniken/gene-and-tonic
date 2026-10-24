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

function unitInteraction(array $overrides = []): array
{
    return array_merge([
        'gene' => 'mthfr', 'rsid' => 'rs1801133', 'allele' => 't', 'relation' => 'helps',
        'explanation' => 'T carriers may need more folate [S1].', 'source_ids' => ['S1'],
    ], $overrides);
}

it('keeps valid gene interactions, normalising gene and allele and stripping inline markers', function () {
    $findings = ResearchFindingsBuilder::build(
        unitOutput(['gene_interactions' => [unitInteraction(), unitInteraction(['rsid' => null, 'allele' => null, 'relation' => 'Caution', 'source_ids' => ['S2', 'S9']])]]),
        unitSources(),
    );

    expect($findings->geneInteractions)->toBe([
        ['gene' => 'MTHFR', 'rsid' => 'rs1801133', 'allele' => 'T', 'relation' => 'helps', 'explanation' => 'T carriers may need more folate.', 'source_keys' => ['S1']],
        ['gene' => 'MTHFR', 'rsid' => null, 'allele' => null, 'relation' => 'caution', 'explanation' => 'T carriers may need more folate.', 'source_keys' => ['S2']],
    ]);
});

it('drops gene interactions that are uncited or carry invalid fields', function (array $overrides) {
    $findings = ResearchFindingsBuilder::build(unitOutput(['gene_interactions' => [unitInteraction($overrides)]]), unitSources());

    expect($findings->geneInteractions)->toBe([]);
})->with([
    'uncited' => [['source_ids' => ['S7']]],
    'no source ids' => [['source_ids' => []]],
    'relation cure' => [['relation' => 'cure']],
    'html gene' => [['gene' => '<b>X</b>']],
    'bad rsid' => [['rsid' => 'rs1; DROP']],
    'bad allele' => [['allele' => 'Z']],
    'multi-char allele' => [['allele' => 'AG']],
    'blank explanation' => [['explanation' => '  [S1] ']],
    'non-string gene' => [['gene' => ['MTHFR']]],
]);

it('treats missing or malformed gene_interactions as none', function (mixed $value) {
    $findings = ResearchFindingsBuilder::build(unitOutput(['gene_interactions' => $value, 'effects' => []]), unitSources());

    expect($findings->geneInteractions)->toBe([]);
})->with([[null], ['nope'], [['not-an-array']]]);
