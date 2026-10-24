<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Str;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Supplements\Data\SupplementResearchData;
use Spatie\LaravelPdf\Enums\Unit;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Turns completed research into a downloadable PDF in a registered template.
 * Views receive only the DTO and derived scalars, never models.
 */
class SupplementReportRenderer
{
    public function __construct(private TemplateRegistry $templates) {}

    public function render(SupplementResearchData $research, string $template, ?GeneticRelevanceData $relevance = null): PdfBuilder
    {
        $config = $this->templates->get($template);
        [$top, $right, $bottom, $left] = $config['margins'];

        $data = [
            'research' => $research,
            'relevance' => $relevance,
            'template' => $template,
            'label' => $config['label'],
            'generatedAt' => now(),
            'sourceNumbers' => $this->sourceNumbers($research),
        ];

        return Pdf::view('reports::report', $data)
            ->headerView("reports::templates.{$template}.header", $data)
            ->footerView("reports::templates.{$template}.footer", $data)
            ->format($config['format'])
            ->orientation($config['orientation'])
            ->margins($top, $right, $bottom, $left, Unit::Millimeter)
            ->name(Str::slug($research->name).'-'.$template.'.pdf')
            ->download();
    }

    /**
     * A source's citation number is its 1-based position in the source list.
     *
     * @return array<string, int>
     */
    private function sourceNumbers(SupplementResearchData $research): array
    {
        $numbers = [];

        foreach ($research->sources as $index => $source) {
            $numbers[$source->key] ??= $index + 1;
        }

        return $numbers;
    }
}
