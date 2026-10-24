<?php

use Carbon\CarbonImmutable;
use Modules\Reports\Services\SupplementReportRenderer;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\MechanismData;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\ResearchStatus;

/**
 * Opt-in: launches a real Chrome. Run with RUN_CHROME_TESTS=1 (and optionally
 * REPORTS_PDF_OUTPUT_DIR=<dir> to keep the PDFs for a visual check).
 */
function chromeRenderResearch(): SupplementResearchData
{
    return new SupplementResearchData(
        supplementId: 1,
        name: 'Magnesium',
        status: ResearchStatus::Complete,
        summary: 'Magnesium glycinate is well absorbed.',
        safetyNotes: 'Can cause loose stools at high doses.',
        effects: [
            new EffectData('Improves sleep quality', 'Shorter sleep latency', EvidenceGrade::Moderate, ['S1', 'S2']),
            new EffectData('Reduces muscle cramps', null, EvidenceGrade::Limited, ['S3']),
        ],
        mechanisms: [new MechanismData('Acts as an NMDA receptor antagonist', ['S2'])],
        studies: [
            new StudyData('Magnesium and insomnia in the elderly', 'RCT', 'Older adults', 46, 2012, 'Improved sleep efficiency', EvidenceGrade::Moderate, 'S1', '23853635'),
            new StudyData('Cramp prophylaxis review', 'Systematic review', null, null, 2020, null, null, 'S3', null),
        ],
        sources: [
            new SourceData('S1', 'https://pubmed.ncbi.nlm.nih.gov/23853635/', 'Insomnia trial', 'pubmed.ncbi.nlm.nih.gov'),
            new SourceData('S2', 'https://ods.od.nih.gov/factsheets/Magnesium/', 'Magnesium fact sheet', 'ods.od.nih.gov'),
            new SourceData('S3', 'https://www.cochranelibrary.com/cdsr/doi/10.1002/14651858.CD009402', 'Cochrane cramps review', 'cochranelibrary.com'),
        ],
        completedAt: CarbonImmutable::parse('2026-10-20 09:00'),
    );
}

it('renders a real PDF through Chrome for each template', function (string $template) {
    $path = tempnam(sys_get_temp_dir(), 'report-').'.pdf';

    try {
        $start = microtime(true);
        app(SupplementReportRenderer::class)->render(chromeRenderResearch(), $template)->save($path);
        $elapsed = microtime(true) - $start;

        $bytes = file_get_contents($path);

        expect($bytes)->toStartWith('%PDF-')
            ->and($elapsed)->toBeLessThan(10.0);

        fwrite(STDERR, sprintf("[chrome] %s: %.2fs, %s pages, %d bytes\n", $template, $elapsed, preg_match('/\/Count (\d+)/', $bytes, $pages) ? $pages[1] : '?', strlen($bytes)));

        if ($outputDir = getenv('REPORTS_PDF_OUTPUT_DIR')) {
            is_dir($outputDir) || mkdir($outputDir, 0777, true);
            copy($path, rtrim($outputDir, '/')."/{$template}.pdf");
        }
    } finally {
        @unlink($path);
    }
})->with(['clinical', 'modern'])
    ->skip(fn () => getenv('RUN_CHROME_TESTS') !== '1', 'Set RUN_CHROME_TESTS=1 to render real PDFs with Chrome.');
