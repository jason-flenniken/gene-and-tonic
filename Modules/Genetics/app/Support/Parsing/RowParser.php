<?php

namespace Modules\Genetics\Support\Parsing;

interface RowParser
{
    /**
     * Whether this parser understands a file, judged from its first lines
     * (already stripped of line endings and a byte-order mark).
     *
     * @param  list<string>  $headLines
     */
    public function matches(array $headLines): bool;

    public function kind(): ParsedKind;

    /**
     * Streams the file line by line, yielding only valid rows.
     *
     * @return iterable<ParsedVariant>
     */
    public function parse(string $absolutePath): iterable;
}
