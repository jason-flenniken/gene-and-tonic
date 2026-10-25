<?php

namespace Modules\Genetics\Support;

use Illuminate\Support\Facades\Log;
use Modules\Genetics\Enums\ParseMethod;
use Modules\Genetics\Support\Extraction\AiMarkerExtractor;
use Modules\Genetics\Support\Parsing\MarkerCsvParser;
use Modules\Genetics\Support\Parsing\MarkerScan;
use Modules\Genetics\Support\Parsing\ParsedVariant;
use Modules\Intelligence\Exceptions\GenerationFailedException;

/**
 * Reads a marker CSV locally first. When the local parser reads at least
 * genetics.local_min_ratio of the rows with an rsID, its rows stand and only the
 * unread ones go to the model; local wins on the same rsID. Below the ratio the
 * local rows are discarded and every rsID row goes to the model. The model only
 * ever sees the rsID, gene, genotype and marker-name cells, and an rsID it
 * returns that no sent row lists is dropped.
 */
final class MarkerListReader
{
    public function __construct(private readonly AiMarkerExtractor $ai) {}

    /**
     * Null when the list has more marker rows than genetics.max_marker_rows,
     * decided before any model call.
     */
    public function read(MarkerCsvParser $parser, string $absolutePath): ?ReadOutcome
    {
        $scan = $parser->scan($absolutePath, (int) config('genetics.max_marker_rows'));

        if ($scan === null) {
            return null;
        }

        if ($scan->readRatio() < (float) config('genetics.local_min_ratio')) {
            $variants = $this->ai->extractLines($scan->candidateLines())->listedVariants();

            return $this->outcome($scan, ParseMethod::Ai, [], $variants);
        }

        $local = [];

        // A repeated rsID keeps its last row, as storing each row in turn would.
        foreach ($scan->read() as $variant) {
            $local[$variant->rsid] = $variant;
        }

        $leftovers = $scan->leftoverLines();

        if ($leftovers === []) {
            return $this->outcome($scan, ParseMethod::Local, array_values($local), []);
        }

        $added = [];

        foreach ($this->leftoverVariants($leftovers) as $variant) {
            if (! isset($local[$variant->rsid])) {
                $added[$variant->rsid] = $variant;
            }
        }

        return $this->outcome($scan, ParseMethod::LocalAi, array_values($local), array_values($added));
    }

    /**
     * The local rows already stand, so a failed call for the few unread rows
     * skips them instead of failing the upload.
     *
     * @param  list<string>  $lines
     * @return list<ParsedVariant>
     */
    private function leftoverVariants(array $lines): array
    {
        try {
            return $this->ai->extractLines($lines)->listedVariants();
        } catch (GenerationFailedException $e) {
            Log::warning('Unread marker rows could not be sent to the model; keeping the local rows.', ['exception' => $e::class]);

            return [];
        }
    }

    /**
     * @param  list<ParsedVariant>  $local
     * @param  list<ParsedVariant>  $ai
     */
    private function outcome(MarkerScan $scan, ParseMethod $method, array $local, array $ai): ReadOutcome
    {
        return new ReadOutcome(
            [...$local, ...$ai],
            $method,
            count($local),
            count($ai),
            max(0, $scan->rows - count($local) - count($ai)),
        );
    }
}
