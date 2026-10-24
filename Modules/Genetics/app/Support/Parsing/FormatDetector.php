<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * Picks a deterministic parser from a file's first lines. Returns null when no
 * parser fits, and always for a pdf, which goes to the marker extractor.
 */
final class FormatDetector
{
    private const HEAD_LINES = 50;

    /**
     * Most specific first: MarkerCsvParser would also claim a vendor CSV.
     *
     * @var list<RowParser>
     */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new TwentyThreeAndMeParser,
            new AncestryParser,
            new VendorCsvParser,
            new MarkerCsvParser,
        ];
    }

    public function detect(string $absolutePath, string $extension): ?RowParser
    {
        if (strtolower($extension) === 'pdf') {
            return null;
        }

        $head = [];

        foreach (Lines::read($absolutePath) as $line) {
            $head[] = $line;

            if (count($head) === self::HEAD_LINES) {
                break;
            }
        }

        foreach ($this->parsers as $parser) {
            if ($parser->matches($head)) {
                return $parser;
            }
        }

        return null;
    }
}
