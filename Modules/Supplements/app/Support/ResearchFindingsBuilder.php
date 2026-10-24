<?php

namespace Modules\Supplements\Support;

use Modules\Intelligence\Data\SourceDocument;
use Modules\Shared\Support\SourceUrl;
use Modules\Supplements\Enums\EvidenceGrade;
use Modules\Supplements\Enums\GeneRelation;

/**
 * Turns the model's untrusted structured answer into ResearchFindings, keeping only cited findings.
 */
final class ResearchFindingsBuilder
{
    /**
     * @param  array<string, mixed>  $output
     * @param  list<SourceDocument>  $sources
     *
     * @throws UnusableResearchOutput
     */
    public static function build(array $output, array $sources): ResearchFindings
    {
        $summary = $output['summary'] ?? null;
        $safety = $output['safety_notes'] ?? null;

        if (! is_string($summary) || trim($summary) === '' || ! is_string($safety)
            || ! is_array($output['effects'] ?? null)
            || ! is_array($output['mechanisms'] ?? null)
            || ! is_array($output['studies'] ?? null)) {
            throw new UnusableResearchOutput('Missing or mistyped top-level keys.');
        }

        // Sources with an unsafe URL are dropped, so nothing can cite them either.
        $sources = array_values(array_filter(
            $sources,
            fn (SourceDocument $s): bool => SourceUrl::sanitize($s->url) !== null,
        ));

        $byId = [];
        foreach ($sources as $source) {
            $byId[$source->id] = $source;
        }

        $effects = [];
        foreach ($output['effects'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $grade = self::grade($item['evidence_grade'] ?? null);
            $keys = self::validKeys($item['source_ids'] ?? null, $byId);

            if ($grade === null || $keys === []
                || ! is_string($item['claim'] ?? null) || ! is_string($item['outcome'] ?? null)) {
                continue;
            }

            $effects[] = [
                'claim' => self::prose($item['claim'], 500),
                'outcome' => self::prose($item['outcome'], 1000),
                'evidence_grade' => $grade->value,
                'source_keys' => $keys,
            ];
        }

        $mechanisms = [];
        foreach ($output['mechanisms'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $keys = self::validKeys($item['source_ids'] ?? null, $byId);

            if ($keys === [] || ! is_string($item['description'] ?? null)) {
                continue;
            }

            $mechanisms[] = ['description' => self::prose($item['description'], 5000), 'source_keys' => $keys];
        }

        $studies = [];
        foreach ($output['studies'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $grade = self::grade($item['evidence_grade'] ?? null);
            $sourceId = $item['source_id'] ?? null;

            if ($grade === null || ! is_string($sourceId) || ! isset($byId[$sourceId])
                || ! is_string($item['title'] ?? null) || ! is_string($item['design'] ?? null) || ! is_string($item['finding'] ?? null)) {
                continue;
            }

            $population = $item['population'] ?? null;

            $studies[] = [
                'title' => self::cut($item['title'], 500),
                'design' => self::cut($item['design'], 100),
                'population' => is_string($population) && $population !== '' ? self::cut($population, 255) : null,
                'sample_size' => self::positiveInt($item['sample_size'] ?? null),
                'year' => self::year($item['year'] ?? null),
                'finding' => self::prose($item['finding'], 5000),
                'evidence_grade' => $grade->value,
                'source_key' => $sourceId,
                'pmid' => self::pmid($byId[$sourceId]->url, $item['pmid'] ?? null),
            ];
        }

        $geneInteractions = [];
        foreach (is_array($output['gene_interactions'] ?? null) ? $output['gene_interactions'] : [] as $item) {
            $interaction = is_array($item) ? self::geneInteraction($item, $byId) : null;

            if ($interaction !== null) {
                $geneInteractions[] = $interaction;
            }
        }

        return new ResearchFindings(
            summary: self::prose($summary, 10000),
            safetyNotes: self::prose($safety, 10000),
            sources: array_map(fn (SourceDocument $s): array => [
                'source_key' => self::cut($s->id, 10),
                'url' => $s->url,
                'title' => self::cut($s->title, 500),
                'domain' => self::cut($s->domain, 255),
            ], $sources),
            effects: $effects,
            mechanisms: $mechanisms,
            studies: $studies,
            geneInteractions: $geneInteractions,
        );
    }

    /**
     * @param  array<mixed>  $item
     * @param  array<string, SourceDocument>  $byId
     * @return array{gene: string, rsid: ?string, allele: ?string, relation: string, explanation: string, source_keys: list<string>}|null
     */
    private static function geneInteraction(array $item, array $byId): ?array
    {
        $gene = is_string($item['gene'] ?? null) ? strtoupper(trim($item['gene'])) : '';
        $relation = is_string($item['relation'] ?? null) ? GeneRelation::tryFrom(strtolower(trim($item['relation']))) : null;
        $keys = self::validKeys($item['source_ids'] ?? null, $byId);
        $rsid = self::rsid($item['rsid'] ?? null);
        $allele = $rsid === null ? null : self::allele($item['allele'] ?? null);

        if (preg_match('/^[A-Z0-9-]{1,15}$/', $gene) !== 1 || $relation === null || $keys === []
            || ! is_string($item['explanation'] ?? null)) {
            return null;
        }

        $explanation = self::prose($item['explanation'], 1000);

        if ($explanation === '') {
            return null;
        }

        return [
            'gene' => $gene,
            'rsid' => $rsid,
            'allele' => $allele,
            'relation' => $relation->value,
            'explanation' => $explanation,
            'source_keys' => $keys,
        ];
    }

    /**
     * Models write rsIDs as published ("RS1801133", "rs1801133 (C677T)"), so the first rsID token is taken.
     * Without one the interaction stays, matching on the gene alone.
     */
    private static function rsid(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/\brs\d{1,10}\b/', strtolower($value), $m) === 1) {
            return $m[0];
        }

        return null;
    }

    /**
     * Keeps a single effect-allele letter, takes the trailing base of an HGVS-style "C677T" or "677T",
     * and otherwise returns null so the interaction matches on the rsID alone.
     */
    private static function allele(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        if (preg_match('/^[ACGTDI]$/', $value) === 1) {
            return $value;
        }

        return preg_match('/^[ACGT]?\d+([ACGT])$/', $value, $m) === 1 ? $m[1] : null;
    }

    /**
     * @param  array<string, SourceDocument>  $byId
     * @return list<string>
     */
    private static function validKeys(mixed $ids, array $byId): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $keys = [];
        foreach ($ids as $id) {
            if (is_string($id) && isset($byId[$id])) {
                $keys[$id] = $id;
            }
        }

        return array_values($keys);
    }

    private static function grade(mixed $value): ?EvidenceGrade
    {
        return is_string($value) ? EvidenceGrade::tryFrom(strtolower(trim($value))) : null;
    }

    private static function pmid(string $sourceUrl, mixed $modelPmid): ?string
    {
        if (preg_match('#^https://pubmed\.ncbi\.nlm\.nih\.gov/(\d{1,9})/?#', $sourceUrl, $m) === 1) {
            return $m[1];
        }

        return is_string($modelPmid) && preg_match('/^\d{1,9}$/', $modelPmid) === 1 ? $modelPmid : null;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^\d{1,9}$/', trim($value)) === 1) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= 0 && $value <= 4_294_967_295 ? $value : null;
    }

    private static function year(mixed $value): ?int
    {
        $year = self::positiveInt($value);

        return $year !== null && $year >= 1900 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    private static function cut(string $value, int $max): string
    {
        return mb_substr(trim($value), 0, $max);
    }

    /**
     * Citations belong in the source id fields, so inline markers such as "[S1, S2]" or "(S3)"
     * are removed from prose; reports number sources themselves.
     */
    private static function prose(string $value, int $max): string
    {
        $stripped = preg_replace('/\s*[\[(]\s*S\d+(?:\s*[,;]\s*S\d+)*\s*[\])]/u', '', $value);

        return self::cut($stripped ?? $value, $max);
    }
}
