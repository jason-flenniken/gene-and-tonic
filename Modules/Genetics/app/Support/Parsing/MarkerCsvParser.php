<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * A short marker list: a comma-separated header naming an rsID column and a
 * genotype column (case-insensitive aliases), with an optional gene column.
 * Checked last: a header that also has chromosome and position columns is a
 * whole-genome export, which VendorCsvParser claims first.
 */
final class MarkerCsvParser implements RowParser
{
    /** @var list<string> */
    public const RSID = ['rsid', 'snp', 'marker', 'variant'];

    /** @var list<string> */
    public const GENOTYPE = ['genotype', 'result', 'call', 'alleles'];

    /** @var list<string> */
    private const GENE = ['gene', 'symbol'];

    public function matches(array $headLines): bool
    {
        $line = Lines::firstContent($headLines);

        return $line !== null && $this->columns($line) !== null;
    }

    public function kind(): ParsedKind
    {
        return ParsedKind::MarkerList;
    }

    public function parse(string $absolutePath): iterable
    {
        $columns = null;

        foreach (Lines::content(Lines::read($absolutePath)) as $line) {
            if ($columns === null) {
                $columns = $this->columns($line);

                if ($columns === null) {
                    return;
                }

                continue;
            }

            $fields = Lines::csv($line);
            $variant = ParsedVariant::fromRaw(
                $fields[$columns['rsid']] ?? '',
                $fields[$columns['genotype']] ?? '',
                $columns['gene'] === null ? null : ($fields[$columns['gene']] ?? null),
            );

            if ($variant !== null) {
                yield $variant;
            }
        }
    }

    /**
     * @return array{rsid: int, genotype: int, gene: int|null}|null
     */
    private function columns(string $header): ?array
    {
        $names = array_map(strtolower(...), Lines::csv($header));

        $rsid = Lines::columnIndex($names, self::RSID);
        $genotype = Lines::columnIndex($names, self::GENOTYPE);

        if ($rsid === null || $genotype === null) {
            return null;
        }

        return ['rsid' => $rsid, 'genotype' => $genotype, 'gene' => Lines::columnIndex($names, self::GENE)];
    }
}
