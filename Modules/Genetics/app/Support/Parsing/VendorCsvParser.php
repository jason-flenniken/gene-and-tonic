<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * MyHeritage and FamilyTreeDNA: an optional block of `#` comments, then a
 * `RSID,CHROMOSOME,POSITION,RESULT` header and quoted comma-separated rows.
 */
final class VendorCsvParser implements RowParser
{
    private const HEADER = ['RSID', 'CHROMOSOME', 'POSITION', 'RESULT'];

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
            $fields = Lines::csv($line);

            if (count($fields) !== 4 || $this->isHeader($line)) {
                continue;
            }

            $variant = ParsedVariant::fromRaw($fields[0], $fields[3], null);

            if ($variant !== null) {
                yield $variant;
            }
        }
    }

    private function isHeader(string $line): bool
    {
        return array_map(strtoupper(...), Lines::csv($line)) === self::HEADER;
    }
}
