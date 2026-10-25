<?php

namespace Modules\Genetics\Support\Extraction;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Support\ExtractionOutputBuilder;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\PromptRequest;

/**
 * Finds markers in a pdf or unrecognised file. The text is read locally and only
 * lines naming an rsID or a known gene go to the model, in structured calls of at
 * most genetics.extract.max_chars each, up to genetics.extract.max_chunks calls.
 * A provider failure propagates so the upload job can retry or fail generically.
 */
final class AiMarkerExtractor implements MarkerExtractor
{
    public function __construct(
        private readonly TextExtractor $text,
        private readonly TextGenerator $generator,
    ) {}

    public function extract(string $absolutePath, string $extension): array
    {
        return $this->extractLines($this->markerLines($this->text->extract($absolutePath, $extension)))->variants;
    }

    /**
     * Sends already-filtered marker lines to the model, packed into as few calls
     * as max_chars allows. Lines past max_chunks calls are not sent; that is
     * logged with counts only, never line text.
     *
     * @param  list<string>  $lines
     */
    public function extractLines(array $lines): Extraction
    {
        [$chunks, $truncated] = $this->chunks($lines);
        $variants = [];
        $sent = [];

        foreach ($chunks as $chunk) {
            $output = $this->generator->generate(PromptRequest::for(
                'genetics.extract',
                ['lines' => $chunk],
                $this->schema(...),
            ))->structured;

            foreach (ExtractionOutputBuilder::build($output) as $variant) {
                $variants[$variant->rsid] ??= $variant;
            }

            array_push($sent, ...$chunk);
        }

        if ($truncated) {
            Log::warning('Genetic marker extraction hit the chunk cap; some lines were not sent.', [
                'lines' => count($lines),
                'sent' => count($sent),
                'chunks' => count($chunks),
            ]);
        }

        return new Extraction(array_values($variants), $sent, $truncated);
    }

    /**
     * Packs lines in order into chunks whose newline-joined length is at most
     * max_chars. A single longer line is cut to max_chars.
     *
     * @param  list<string>  $lines
     * @return array{list<list<string>>, bool} the chunks, and whether lines were left over
     */
    private function chunks(array $lines): array
    {
        $maxChars = max(1, (int) config('genetics.extract.max_chars'));
        $maxChunks = max(1, (int) config('genetics.extract.max_chunks'));
        $chunks = [];
        $chunk = [];
        $used = 0;

        foreach ($lines as $line) {
            $line = mb_substr($line, 0, $maxChars);
            $length = mb_strlen($line);

            if ($chunk !== [] && $used + 1 + $length > $maxChars) {
                $chunks[] = $chunk;
                $chunk = [];
                $used = 0;
            }

            if (count($chunks) === $maxChunks) {
                return [$chunks, true];
            }

            $used += ($chunk === [] ? 0 : 1) + $length;
            $chunk[] = $line;
        }

        if ($chunk !== []) {
            $chunks[] = $chunk;
        }

        return [$chunks, false];
    }

    /**
     * @return list<string>
     */
    private function markerLines(string $text): array
    {
        $genes = array_flip(GeneVariant::whereNotNull('gene')->distinct()->pluck('gene')->all());
        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');

            if ($line !== '' && $this->isMarkerLine($line, $genes)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, int>  $genes  known gene symbols as keys
     */
    private function isMarkerLine(string $line, array $genes): bool
    {
        if (preg_match('/\brs\d{1,10}\b/i', $line) === 1) {
            return true;
        }

        foreach (preg_split('/[^A-Za-z0-9-]+/', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (isset($genes[$word])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strict schema: every property is required; the gene is nullable.
     *
     * @return array<string, mixed>
     */
    private function schema(JsonSchema $s): array
    {
        return [
            'variants' => $s->array()->items($s->object([
                'rsid' => $s->string()->required(),
                'gene' => $s->string()->nullable()->required(),
                'genotype' => $s->string()->required(),
            ]))->required(),
        ];
    }
}
