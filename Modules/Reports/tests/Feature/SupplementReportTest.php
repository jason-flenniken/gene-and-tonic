<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\View;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\MechanismData;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * A completed research DTO: 2 effects, 1 mechanism, 2 studies, 3 sources.
 *
 * @param  array<string, mixed>  $overrides
 */
function reportResearch(array $overrides = []): SupplementResearchData
{
    return new SupplementResearchData(...array_merge([
        'supplementId' => 1,
        'name' => 'Magnesium',
        'status' => ResearchStatus::Complete,
        'summary' => 'Magnesium glycinate is well absorbed.',
        'safetyNotes' => 'Can cause loose stools at high doses.',
        'effects' => [
            new EffectData('Improves sleep quality', 'Shorter sleep latency', EvidenceGrade::Moderate, ['S1', 'S2']),
            new EffectData('Reduces muscle cramps', null, EvidenceGrade::Limited, ['S3']),
        ],
        'mechanisms' => [
            new MechanismData('Acts as an NMDA receptor antagonist', ['S2']),
        ],
        'studies' => [
            new StudyData('Magnesium and insomnia in the elderly', 'RCT', 'Older adults', 46, 2012, 'Improved sleep efficiency', EvidenceGrade::Moderate, 'S1', '23853635'),
            new StudyData('Cramp prophylaxis review', 'Systematic review', null, null, 2020, null, null, 'S3', null),
        ],
        'sources' => [
            new SourceData('S1', 'https://pubmed.ncbi.nlm.nih.gov/23853635/', 'Insomnia trial', 'pubmed.ncbi.nlm.nih.gov'),
            new SourceData('S2', 'https://ods.od.nih.gov/factsheets/Magnesium/', 'Magnesium fact sheet', 'ods.od.nih.gov'),
            new SourceData('S3', 'https://www.cochranelibrary.com/cdsr/doi/10.1002/14651858.CD009402', 'Cochrane cramps review', 'cochranelibrary.com'),
        ],
        'completedAt' => CarbonImmutable::parse('2026-10-20 09:00'),
    ], $overrides));
}

function reportUrl(int $supplementId = 1, array $query = []): string
{
    return route('reports.supplements.pdf', ['supplement' => $supplementId, ...$query]);
}

/**
 * Pest's negated toContain() passes when any one needle is missing, so check each.
 */
function expectAbsent(?string $html, string ...$needles): void
{
    foreach ($needles as $needle) {
        expect($html)->not->toContain($needle);
    }
}

function assertNoPdfResponded(): void
{
    expect(fn () => Pdf::assertRespondedWithPdf(fn () => true))->toThrow(AssertionFailedError::class);
}

beforeEach(function () {
    Pdf::fake();
    $this->reader = FakeSupplementResearchReader::install();
});

it('returns 404 for an unknown supplement', function () {
    $this->reader->with(reportResearch());

    $this->get(reportUrl(99))->assertNotFound();
    $this->get('/reports/supplements/99999999999999999999999.pdf')->assertNotFound();

    assertNoPdfResponded();
});

it('returns 404 for a supplement with no research', function () {
    $this->get(reportUrl())->assertNotFound();

    assertNoPdfResponded();
});

it('returns 404 while research is still running', function (ResearchStatus $status) {
    $this->reader->with(reportResearch(['status' => $status]));

    $this->get(reportUrl())->assertNotFound();

    assertNoPdfResponded();
})->with([ResearchStatus::Queued, ResearchStatus::Searching, ResearchStatus::Analyzing]);

it('returns 404 for failed research without leaking the error', function () {
    $this->reader->with(reportResearch(['status' => ResearchStatus::Failed, 'error' => 'Provider key sk-secret rejected']));

    $this->get(reportUrl())->assertNotFound()->assertDontSee('sk-secret');

    assertNoPdfResponded();
});

it('returns 404 for an unknown template and renders nothing', function (mixed $template) {
    $this->reader->with(reportResearch());

    $this->get(reportUrl(query: ['template' => $template]))->assertNotFound();

    assertNoPdfResponded();
    Pdf::assertNotQueued();
})->with([
    'unlisted name' => ['nope'],
    'view path traversal' => ['../../welcome'],
    'view name' => ['welcome'],
    'array value' => [['clinical']],
]);

it('downloads the clinical report with every section', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-24 15:30'));
    $this->reader->with(reportResearch());

    $this->get(reportUrl(query: ['template' => 'clinical']))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        $html = $pdf->getHtml();

        expect($pdf->isDownload())->toBeTrue()
            ->and($pdf->downloadName)->toBe('magnesium-clinical.pdf')
            ->and($pdf->viewName)->toBe('reports::report')
            ->and($pdf->format)->toBe('a4')
            ->and($pdf->orientation)->toBe('portrait')
            ->and($pdf->margins)->toEqual(['top' => 22, 'right' => 16, 'bottom' => 22, 'left' => 16, 'unit' => 'mm'])
            ->and($html)->toContain(
                'Magnesium',
                'Supplement research report',
                'October 24, 2026',
                'Clinical',
                'Magnesium glycinate is well absorbed.',
                'Can cause loose stools at high doses.',
                'Improves sleep quality',
                'Shorter sleep latency',
                'Moderate',
                'Limited',
                'Acts as an NMDA receptor antagonist',
                'Magnesium and insomnia in the elderly',
                'RCT',
                'Older adults',
                '46',
                '2012',
                'Improved sleep efficiency',
                'Systematic review',
                'PMID 23853635',
                'href="https://pubmed.ncbi.nlm.nih.gov/23853635/"',
                'Insomnia trial',
                'ods.od.nih.gov',
                'href="https://ods.od.nih.gov/factsheets/Magnesium/"',
                'This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.',
                '<style',
            )
            // Effect 1 cites S1 and S2, effect 2 cites S3, the mechanism cites S2.
            ->and($html)->toContain('<sup class="cite">[1] [2]</sup>', '<sup class="cite">[3]</sup>', '<sup class="cite">[2]</sup>')
            // Sources are numbered by their position.
            ->and($html)->toContain('<span class="source-number">[1]</span>', '<span class="source-number">[2]</span>', '<span class="source-number">[3]</span>')
            ->and($pdf->getHeaderHtml())->toContain('Magnesium', 'font-size')
            ->and($pdf->getFooterHtml())->toContain('class="pageNumber"', 'class="totalPages"', 'font-size');

        return true;
    });
});

it('uses the clinical template when none is requested', function () {
    $this->reader->with(reportResearch());

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->downloadName === 'magnesium-clinical.pdf' && $pdf->format === 'a4');
});

it('uses the configured default template when none is requested', function () {
    View::prependNamespace('reports', __DIR__.'/../fixtures/views');
    config([
        'reports.templates.testing' => ['label' => 'Testing', 'format' => 'letter', 'margins' => [10, 10, 10, 10], 'orientation' => 'landscape'],
        'reports.default_template' => 'testing',
    ]);
    $this->reader->with(reportResearch());

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->downloadName)->toBe('magnesium-testing.pdf')
            ->and($pdf->format)->toBe('letter')
            ->and($pdf->orientation)->toBe('landscape')
            ->and($pdf->getHtml())->toContain('data-template="testing"', 'Improves sleep quality');

        return true;
    });
});

it('shows fallbacks for empty sections', function () {
    $this->reader->with(reportResearch([
        'summary' => null,
        'safetyNotes' => null,
        'effects' => [],
        'mechanisms' => [],
        'studies' => [],
        'sources' => [],
    ]));

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain(
            'No summary available.',
            'No safety notes recorded.',
            'No effects recorded.',
            'No mechanisms recorded.',
            'No studies recorded.',
            'No sources recorded.',
        );

        return true;
    });
});

it('escapes hostile model and scraped text', function () {
    $this->reader->with(reportResearch([
        'name' => 'Magnesium <b>bold</b>',
        'effects' => [new EffectData('Claim <img src=http://169.254.169.254/>', null, EvidenceGrade::Strong, ['S1'])],
        'sources' => [new SourceData('S1', 'https://example.org/', '<script>alert(1)</script>', 'example.org')],
    ]));

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        foreach ([$pdf->getHtml(), $pdf->getHeaderHtml(), $pdf->getFooterHtml()] as $html) {
            expectAbsent($html, '<script', '<link', 'src="http', 'url(http', '<b>bold</b>', '<img');
        }

        expect($pdf->getHtml())->toContain('&lt;script&gt;alert(1)&lt;/script&gt;', 'Claim &lt;img src=http://169.254.169.254/&gt;')
            ->and($pdf->getHeaderHtml())->toContain('Magnesium &lt;b&gt;bold&lt;/b&gt;');

        return true;
    });
});

it('renders links only for safe source URLs and numeric PMIDs', function () {
    $this->reader->with(reportResearch([
        'studies' => [new StudyData('Odd PMID study', 'RCT', null, null, null, null, null, 'S1', '12a')],
        'sources' => [new SourceData('S1', 'javascript:alert(1)', 'Script source', 'evil.example')],
    ]));

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain('Script source', 'Odd PMID study');
        expectAbsent($pdf->getHtml(), 'href', 'javascript:', 'pubmed.ncbi.nlm.nih.gov', 'PMID 12a');

        return true;
    });
});

it('drops citation markers for unknown source keys', function () {
    $this->reader->with(reportResearch([
        'effects' => [new EffectData('Uncited claim', null, EvidenceGrade::Limited, ['S99'])],
        'mechanisms' => [new MechanismData('Partly cited mechanism', ['S99', 'S3'])],
        'studies' => [],
    ]));

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain('Partly cited mechanism <sup class="cite">[3]</sup>');
        expectAbsent($pdf->getHtml(), 'Uncited claim <sup', 'S99');

        return true;
    });
});

it('never renders the research error field', function () {
    $this->reader->with(reportResearch(['error' => 'Internal provider trace sk-secret']));

    $this->get(reportUrl())->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        foreach ([$pdf->getHtml(), $pdf->getHeaderHtml(), $pdf->getFooterHtml()] as $html) {
            expectAbsent($html, 'sk-secret');
        }

        return true;
    });
});

it('throttles downloads to 10 a minute per IP', function () {
    $this->reader->with(reportResearch());

    foreach (range(1, 10) as $attempt) {
        $this->get(reportUrl())->assertOk();
    }

    $this->get(reportUrl())->assertTooManyRequests();
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->get(reportUrl())->assertOk();
});
