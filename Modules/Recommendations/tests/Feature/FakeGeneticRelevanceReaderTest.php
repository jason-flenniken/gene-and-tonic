<?php

use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Testing\FakeGeneticRelevanceReader;
use Modules\Supplements\Enums\GeneRelation;

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
