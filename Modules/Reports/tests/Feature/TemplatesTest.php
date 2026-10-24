<?php

use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Modules\Reports\Tests\Support\ReportFixtures;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\SourceData;
use Modules\Supplements\Data\StudyData;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Testing\FakeSupplementResearchReader;
use Spatie\LaravelPdf\Facades\Pdf;

beforeEach(function () {
    Pdf::fake();
    $this->reader = FakeSupplementResearchReader::install();
});

/**
 * @return list<string>
 */
function configuredTemplateKeys(): array
{
    return array_keys(config('reports.templates'));
}

it('downloads the modern report as Letter with studies as cards', function () {
    $this->reader->with(ReportFixtures::research());

    $this->get(ReportFixtures::url(query: ['template' => 'modern']))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        $html = $pdf->getHtml();

        expect($pdf->downloadName)->toBe('magnesium-modern.pdf')
            ->and($pdf->format)->toBe('letter')
            ->and($pdf->orientation)->toBe('portrait')
            ->and($pdf->margins)->toEqual(['top' => 20, 'right' => 18, 'bottom' => 20, 'left' => 18, 'unit' => 'mm'])
            ->and($html)->toContain('class="study-card"', 'Magnesium and insomnia in the elderly', 'PMID 23853635', 'Improved sleep efficiency', 'Modern');
        ReportFixtures::expectAbsent($html, '<table', 'class="studies"');

        return true;
    });
});

it('keeps the shared studies table for the clinical template', function () {
    $this->reader->with(ReportFixtures::research());

    $this->get(ReportFixtures::url(query: ['template' => 'clinical']))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->getHtml())->toContain('<table class="studies">');
        ReportFixtures::expectAbsent($pdf->getHtml(), 'study-card');

        return true;
    });
});

it('gives every configured template the visible title block, disclaimer, header, footer and inlined CSS', function () {
    $this->reader->with(ReportFixtures::research());

    foreach (configuredTemplateKeys() as $key) {
        $config = config("reports.templates.{$key}");

        $this->get(ReportFixtures::url(query: ['template' => $key]))->assertOk();

        Pdf::assertRespondedWithPdf(function ($pdf) use ($key, $config) {
            if ($pdf->downloadName !== "magnesium-{$key}.pdf") {
                return false;
            }

            expect($pdf->format)->toBe($config['format'])
                ->and($pdf->getHtml())->toContain(ReportFixtures::DISCLAIMER, '<style')
                ->and(preg_replace('#<head>.*</head>#s', '', $pdf->getHtml()))
                ->toContain('<p class="eyebrow">Supplement research report</p>', now()->format('F j, Y'), $config['label'])
                ->and($pdf->getHeaderHtml())->toContain('Magnesium', 'font-size')
                ->and($pdf->getFooterHtml())->toContain('class="pageNumber"', 'class="totalPages"', 'font-size');

            return true;
        });
    }
});

it('renders a test-only template that is just a view folder and a config line', function () {
    View::prependNamespace('reports', __DIR__.'/../fixtures/views');
    config(['reports.templates.testing' => ['label' => 'Testing', 'format' => 'a5', 'margins' => [5, 6, 7, 8], 'orientation' => 'portrait']]);
    $this->reader->with(ReportFixtures::research());

    $this->get(ReportFixtures::url(query: ['template' => 'testing']))->assertOk();

    Pdf::assertRespondedWithPdf(function ($pdf) {
        expect($pdf->format)->toBe('a5')
            ->and($pdf->margins)->toEqual(['top' => 5, 'right' => 6, 'bottom' => 7, 'left' => 8, 'unit' => 'mm'])
            ->and($pdf->getHtml())->toContain('data-template="testing"');

        return true;
    });
});

it('escapes hostile text in every template with no scripts, links or remote assets', function () {
    $this->reader->with(ReportFixtures::research([
        'name' => '<script>alert(1)</script>',
        'effects' => [new EffectData('Claim <img src=http://169.254.169.254/>', null, EvidenceGrade::Strong, ['S1'])],
        'studies' => [new StudyData('<script>study()</script>', 'RCT <b>x</b>', '<img src=x>', 5, 2020, '<img src=http://169.254.169.254/>', EvidenceGrade::Limited, 'S1', '1<script>')],
        'sources' => [new SourceData('S1', 'javascript:alert(1)', '<script>alert(2)</script>', 'evil.example')],
    ]));

    foreach (configuredTemplateKeys() as $key) {
        $this->get(ReportFixtures::url(query: ['template' => $key]))->assertOk();

        Pdf::assertRespondedWithPdf(function ($pdf) use ($key) {
            if ($pdf->downloadName !== Str::slug('<script>alert(1)</script>')."-{$key}.pdf") {
                return false;
            }

            foreach ([$pdf->getHtml(), $pdf->getHeaderHtml(), $pdf->getFooterHtml()] as $html) {
                ReportFixtures::expectAbsent($html, '<script', '<link', 'src="http', 'url(http', '<img', '<b>x</b>', 'javascript:', 'href="javascript');
            }

            expect($pdf->getHtml())->toContain('&lt;script&gt;study()&lt;/script&gt;', '&lt;script&gt;alert(2)&lt;/script&gt;')
                ->and($pdf->getHeaderHtml())->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');

            return true;
        });
    }
});
