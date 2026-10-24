<?php

namespace Modules\Genetics\Support;

use InvalidArgumentException;
use Modules\Genetics\Data\Genotype;
use Modules\Intelligence\Data\SourceDocument;
use Modules\Shared\Support\SourceUrl;

/**
 * Turns the model's untrusted structured answer into a LookupResult, keeping only valid, cited data.
 */
final class LookupResultBuilder
{
    private const MAX_EFFECTS = 12;

    /**
     * @param  array<string, mixed>  $output
     * @param  list<SourceDocument>  $sources
     *
     * @throws UnusableLookupOutput
     */
    public static function build(array $output, array $sources): LookupResult
    {
        $byId = [];
        foreach ($sources as $source) {
            $byId[$source->id] = $source;
        }

        $cited = self::citedSources($output['source_ids'] ?? null, $byId);

        if ($cited === []) {
            throw new UnusableLookupOutput('No valid cited source.');
        }

        $category = self::text($output['category'] ?? null, 60);
        $affects = self::text($output['affects'] ?? null, 1000);

        if ($category === '' || $affects === '') {
            throw new UnusableLookupOutput('Missing category or affects.');
        }

        $reference = self::allele($output['reference_allele'] ?? null);
        $risk = self::allele($output['risk_allele'] ?? null);

        if ($reference !== null && $reference === $risk) {
            throw new UnusableLookupOutput('Reference and risk alleles are the same.');
        }

        return new LookupResult(
            gene: self::gene($output['gene'] ?? null),
            category: $category,
            affects: $affects,
            referenceAllele: $reference,
            riskAllele: $risk,
            genotypeEffects: self::effects($output['genotype_effects'] ?? null),
            sources: $cited,
        );
    }

    /**
     * Only cited sources with a plain http(s) URL are kept.
     *
     * @param  array<string, SourceDocument>  $byId
     * @return list<array{url: string, title: string, domain: string}>
     */
    private static function citedSources(mixed $ids, array $byId): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $kept = [];
        foreach ($ids as $id) {
            $source = is_string($id) ? ($byId[$id] ?? null) : null;

            $url = $source === null ? null : SourceUrl::sanitize($source->url);

            if ($source === null || $url === null) {
                continue;
            }

            $kept[$url] ??= [
                'url' => $url,
                'title' => mb_substr(trim($source->title), 0, 500),
                'domain' => mb_substr(trim($source->domain), 0, 255),
            ];
        }

        return array_values($kept);
    }

    private static function gene(mixed $value): ?string
    {
        $gene = is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[A-Z0-9-]{1,15}$/', $gene) === 1 ? $gene : null;
    }

    private static function allele(mixed $value): ?string
    {
        $allele = is_string($value) ? strtoupper(trim($value)) : '';

        return preg_match('/^[ACGT]$/', $allele) === 1 ? $allele : null;
    }

    /**
     * Keys are normalized so the profile reader's lookup by the stored genotype finds them.
     *
     * @return array<string, string>
     */
    private static function effects(mixed $items): array
    {
        $effects = [];

        foreach (is_array($items) ? $items : [] as $item) {
            if (! is_array($item) || ! is_string($item['genotype'] ?? null)) {
                continue;
            }

            try {
                $genotype = Genotype::normalize($item['genotype']);
            } catch (InvalidArgumentException) {
                continue;
            }

            $effect = self::text($item['effect'] ?? null, 500);

            if ($genotype === null || $effect === '') {
                continue;
            }

            $effects[$genotype] ??= $effect;

            if (count($effects) === self::MAX_EFFECTS) {
                break;
            }
        }

        return $effects;
    }

    /**
     * Citations belong in source_ids, so inline markers such as "[S1, S2]" or "(S3)" are removed.
     */
    private static function text(mixed $value, int $max): string
    {
        if (! is_string($value)) {
            return '';
        }

        $stripped = preg_replace('/\s*[\[(]\s*S\d+(?:\s*[,;]\s*S\d+)*\s*[\])]/u', '', $value);

        return mb_substr(trim($stripped ?? $value), 0, $max);
    }
}
