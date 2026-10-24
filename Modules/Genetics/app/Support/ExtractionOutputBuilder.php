<?php

namespace Modules\Genetics\Support;

use Modules\Genetics\Support\Parsing\ParsedVariant;

/**
 * Turns the model's untrusted extraction answer into validated rows. Anything
 * that fails the rsID, genotype or gene patterns is dropped, never repaired.
 */
final class ExtractionOutputBuilder
{
    /**
     * @param  array<string, mixed>  $output
     * @return list<ParsedVariant>
     */
    public static function build(array $output): array
    {
        $items = $output['variants'] ?? null;
        $variants = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || ! is_string($item['rsid'] ?? null) || ! is_string($item['genotype'] ?? null)) {
                continue;
            }

            $gene = $item['gene'] ?? null;
            $variant = ParsedVariant::fromRaw($item['rsid'], $item['genotype'], is_string($gene) ? $gene : null);

            if ($variant !== null) {
                $variants[$variant->rsid] ??= $variant;
            }
        }

        return array_values($variants);
    }
}
