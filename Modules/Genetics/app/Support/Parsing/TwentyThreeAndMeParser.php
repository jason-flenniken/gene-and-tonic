<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * 23andMe: `#` comments, then tab-separated `rsid chromosome position genotype`.
 */
final class TwentyThreeAndMeParser implements RowParser
{
    public function matches(array $headLines): bool
    {
        $line = Lines::firstContent($headLines);

        if ($line === null) {
            return false;
        }

        $fields = explode("\t", $line);

        return count($fields) === 4 && preg_match('/^(rs|i)\d+$/i', trim($fields[0])) === 1;
    }

    public function kind(): ParsedKind
    {
        return ParsedKind::RawExport;
    }

    public function parse(string $absolutePath): iterable
    {
        foreach (Lines::content(Lines::read($absolutePath)) as $line) {
            $fields = explode("\t", $line);

            if (count($fields) !== 4) {
                continue;
            }

            $variant = ParsedVariant::fromRaw($fields[0], $fields[3], null);

            if ($variant !== null) {
                yield $variant;
            }
        }
    }
}
