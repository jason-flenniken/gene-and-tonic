<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * Whole-genome CSVs: MyHeritage and FamilyTreeDNA (`RSID,CHROMOSOME,POSITION,RESULT`)
 * and any other CSV whose header has chromosome and position columns beside an
 * rsID and a genotype column. An optional block of `#` comments comes first.
 * Values may be quoted.
 */
final class VendorCsvParser implements RowParser
{
    public function matches(array $headLines): bool
    {
        $line = Lines::firstContent($headLines);

        return $line !== null && $this->columns($line) !== null;
    }

    public function kind(): ParsedKind
    {
        return ParsedKind::RawExport;
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
            $variant = ParsedVariant::fromRaw($fields[$columns['rsid']] ?? '', $fields[$columns['genotype']] ?? '', null);

            if ($variant !== null) {
                yield $variant;
            }
        }
    }

    /**
     * @return array{rsid: int, genotype: int}|null
     */
    private function columns(string $header): ?array
    {
        $names = array_map(strtolower(...), Lines::csv($header));
        $rsid = Lines::columnIndex($names, MarkerCsvParser::RSID);
        $genotype = Lines::columnIndex($names, MarkerCsvParser::GENOTYPE);

        if ($rsid === null || $genotype === null || ! in_array('chromosome', $names, true) || ! in_array('position', $names, true)) {
            return null;
        }

        return ['rsid' => $rsid, 'genotype' => $genotype];
    }
}
