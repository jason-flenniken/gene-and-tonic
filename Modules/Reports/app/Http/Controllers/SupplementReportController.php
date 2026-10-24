<?php

namespace Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Reports\Services\SupplementReportRenderer;
use Modules\Reports\Services\TemplateRegistry;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Enums\ResearchStatus;
use Spatie\LaravelPdf\PdfBuilder;

class SupplementReportController extends Controller
{
    /**
     * Download a supplement's completed research as a PDF. The template must be
     * registered in config, so the query value never becomes a view path.
     */
    public function __invoke(
        Request $request,
        string $supplement,
        TemplateRegistry $templates,
        SupplementResearchReader $reader,
        SupplementReportRenderer $renderer,
    ): PdfBuilder {
        $template = $request->query('template', $templates->default());

        if (! is_string($template) || ! $templates->has($template)) {
            abort(404);
        }

        $supplementId = filter_var($supplement, FILTER_VALIDATE_INT);

        if ($supplementId === false) {
            abort(404);
        }

        $research = $reader->forSupplement($supplementId);

        if ($research === null || $research->status !== ResearchStatus::Complete) {
            abort(404);
        }

        return $renderer->render($research, $template);
    }
}
