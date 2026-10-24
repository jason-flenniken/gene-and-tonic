<?php

namespace Modules\Genetics\Support\Extraction;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Support\ExtractionOutputBuilder;
use Modules\Intelligence\Contracts\TextGenerator;
use Modules\Intelligence\Data\PromptRequest;

/**
 * Finds markers in a pdf or unrecognised file. The text is read locally and only
 * lines naming an rsID or a known gene go to the model, in one structured call.
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
        $lines = $this->markerLines($this->text->extract($absolutePath, $extension));

        if ($lines === []) {
            return [];
        }

        $output = $this->generator->generate(PromptRequest::for(
            'genetics.extract',
            ['lines' => $lines],
            $this->schema(...),
        ))->structured;

        return ExtractionOutputBuilder::build($output);
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

        $max = max(0, (int) config('genetics.extract.max_chars'));
        $kept = [];
        $used = 0;

        foreach ($lines as $line) {
            $room = $max - $used;

            if ($room <= 0) {
                break;
            }

            $line = mb_substr($line, 0, $room);

            $kept[] = $line;
            $used += mb_strlen($line) + 1;
        }

        return $kept;
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
