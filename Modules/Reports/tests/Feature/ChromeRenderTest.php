<?php

use Modules\Reports\Services\SupplementReportRenderer;
use Modules\Reports\Tests\Support\ReportFixtures;

/**
 * Opt-in: launches a real Chrome. Run with RUN_CHROME_TESTS=1 (and optionally
 * REPORTS_PDF_OUTPUT_DIR=<dir> to keep the PDFs for a visual check).
 */
it('renders a real PDF through Chrome for each template', function (string $template) {
    $path = tempnam(sys_get_temp_dir(), 'report-').'.pdf';

    try {
        $start = microtime(true);
        app(SupplementReportRenderer::class)->render(ReportFixtures::research(), $template)->save($path);
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
