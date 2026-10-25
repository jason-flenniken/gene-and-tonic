<?php

use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;

function fakeRelevance(int $supplementId): GeneticRelevanceData
{
    return new GeneticRelevanceData($supplementId, true, true, [
        new GeneticMatchData('MTHFR', 'rs1801133', 'AG', 1, GeneRelation::Helps, 'Explained.', ['S1'], 'allele'),
    ]);
}

it('binds itself as the relevance reader', function () {
    $fake = FakeGeneticRelevanceReader::install();

    expect(app(GeneticRelevanceReader::class))->toBe($fake);
});

it('returns the stored relevance for a user and supplement', function () {
    $data = fakeRelevance(5);

    FakeGeneticRelevanceReader::install()->for(3, 5, $data)->for(4, 5, fakeRelevance(5));

    expect(app(GeneticRelevanceReader::class)->forUserAndSupplement(3, 5))->toBe($data);
});

it('returns no profile and no matches for an unknown pair', function () {
    FakeGeneticRelevanceReader::install()->for(3, 5, fakeRelevance(5));

    $relevance = app(GeneticRelevanceReader::class)->forUserAndSupplement(3, 6);

    expect($relevance->supplementId)->toBe(6)
        ->and($relevance->hasProfile)->toBeFalse()
        ->and($relevance->geneticsChecked)->toBeFalse()
        ->and($relevance->hasMatches())->toBeFalse();
});

function fakeTop(int $supplementId, string $name, int $matches): SupplementMatchesData
{
    $match = new GeneticMatchData('MTHFR', 'rs1801133', 'AG', null, GeneRelation::Helps, 'Explained.', ['S1'], 'gene');

    return new SupplementMatchesData($supplementId, $name, ResearchStatus::Complete, array_fill(0, $matches, $match));
}

it('ranks the stored top supplements by match count then name, without empty ones', function () {
    FakeGeneticRelevanceReader::install()->top(3, [
        fakeTop(1, 'Zinc', 2),
        fakeTop(2, 'Methylfolate', 3),
        fakeTop(3, 'B12', 0),
        fakeTop(4, 'riboflavin', 2),
    ]);

    $top = app(GeneticRelevanceReader::class)->topForUser(3, 5);

    expect(array_map(fn (SupplementMatchesData $supplement) => $supplement->name, $top))->toBe(['Methylfolate', 'riboflavin', 'Zinc']);
});

it('applies the limit, one by default', function () {
    FakeGeneticRelevanceReader::install()->top(3, [fakeTop(1, 'Zinc', 2), fakeTop(2, 'Methylfolate', 3)]);

    $reader = app(GeneticRelevanceReader::class);

    expect($reader->topForUser(3))->toHaveCount(1)
        ->and($reader->topForUser(3)[0]->name)->toBe('Methylfolate')
        ->and($reader->topForUser(3, 2))->toHaveCount(2);
});

it('returns no top supplements for an unknown user', function () {
    FakeGeneticRelevanceReader::install()->top(3, [fakeTop(1, 'Zinc', 2)]);

    expect(app(GeneticRelevanceReader::class)->topForUser(4, 5))->toBe([]);
});

it('carries the allele and variant name through the fake', function () {
    $match = new GeneticMatchData('MTHFR', 'rs1801133', 'AG', 1, GeneRelation::Helps, 'Explained.', ['S1'], 'allele', 'A', 'C677T');

    FakeGeneticRelevanceReader::install()->for(3, 5, new GeneticRelevanceData(5, true, true, [$match]));

    $stored = app(GeneticRelevanceReader::class)->forUserAndSupplement(3, 5)->matches[0];

    expect($stored->allele)->toBe('A')
        ->and($stored->variantName)->toBe('C677T');
});
