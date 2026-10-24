<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * A short marker list: a comma-separated header naming an rsID column and a
 * genotype column (case-insensitive aliases), with an optional gene column.
 * Checked last, because a vendor CSV header also matches these aliases.
 */
final class MarkerCsvParser implements RowParser
{
    private const RSID = ['rsid', 'snp', 'marker', 'variant'];

    private const GENOTYPE = ['genotype', 'result', 'call', 'alleles'];

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

        $find = function (array $aliases) use ($names): ?int {
            foreach ($names as $index => $name) {
                if (in_array($name, $aliases, true)) {
                    return $index;
                }
            }

            return null;
        };

        $rsid = $find(self::RSID);
        $genotype = $find(self::GENOTYPE);

        if ($rsid === null || $genotype === null) {
            return null;
        }

        return ['rsid' => $rsid, 'genotype' => $genotype, 'gene' => $find(self::GENE)];
    }
}
