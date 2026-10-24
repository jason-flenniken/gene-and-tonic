<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * AncestryDNA: `#` comments, a `rsid chromosome position allele1 allele2`
 * header, then tab-separated rows with the two alleles in separate columns.
 * A `0 0` row is a no-call. Chromosomes 23-26 (X, Y, XY, MT) parse like any other.
 */
final class AncestryParser implements RowParser
{
    private const HEADER = ['rsid', 'chromosome', 'position', 'allele1', 'allele2'];

    public function matches(array $headLines): bool
    {
        $line = Lines::firstContent($headLines);

        return $line !== null && $this->isHeader($line);
    }

    public function kind(): ParsedKind
    {
        return ParsedKind::RawExport;
    }

    public function parse(string $absolutePath): iterable
    {
        foreach (Lines::content(Lines::read($absolutePath)) as $line) {
            $fields = explode("\t", $line);

            if (count($fields) !== 5 || $this->isHeader($line)) {
                continue;
            }

            $variant = ParsedVariant::fromRaw($fields[0], trim($fields[3]).trim($fields[4]), null);

            if ($variant !== null) {
                yield $variant;
            }
        }
    }

    private function isHeader(string $line): bool
    {
        return array_map(fn (string $field): string => strtolower(trim($field)), explode("\t", $line)) === self::HEADER;
    }
}
