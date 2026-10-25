<?php

use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;

function fakeReaderResearch(int $supplementId): SupplementResearchData
{
    return new SupplementResearchData(
        supplementId: $supplementId,
        name: 'Magnesium',
        status: ResearchStatus::Complete,
        summary: null,
        safetyNotes: null,
        effects: [],
        mechanisms: [],
        studies: [],
        sources: [],
        completedAt: null,
    );
}

it('binds itself as the research reader', function () {
    $fake = FakeSupplementResearchReader::install();

    expect(app(SupplementResearchReader::class))->toBe($fake);
});

it('returns stored research by supplement id', function () {
    $research = fakeReaderResearch(7);

    FakeSupplementResearchReader::install()->with($research)->with(fakeReaderResearch(8));

    expect(app(SupplementResearchReader::class)->forSupplement(7))->toBe($research);
});

it('returns null for a supplement it does not know', function () {
    FakeSupplementResearchReader::install()->with(fakeReaderResearch(7));

    expect(app(SupplementResearchReader::class)->forSupplement(99))->toBeNull();
});

it('lists the summaries it was given', function () {
    $summary = new SupplementSummaryData(7, 'Magnesium', ResearchStatus::Complete, null, 1, 2, 3);

    FakeSupplementResearchReader::install()->withSummary($summary);

    expect(app(SupplementResearchReader::class)->all())->toBe([$summary]);
});
