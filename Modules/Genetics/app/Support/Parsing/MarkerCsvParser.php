<?php

namespace Modules\Genetics\Support\Parsing;

use Generator;
use InvalidArgumentException;
use Modules\Genetics\Data\Genotype;

/**
 * A short marker list: a comma-separated header naming an rsID column and a
 * genotype column, with an optional gene column. A header name matches when any
 * part of it, split on `/ | ; , ( )`, is an alias ("Genotype / Result" is a
 * genotype column). Aliases are tried in priority order, so an `rsID` column
 * wins over an earlier `Marker` column. Checked last: a header that also has
 * chromosome and position columns is a whole-genome export, which
 * VendorCsvParser claims first.
 */
final class MarkerCsvParser implements RowParser
{
    /** @var list<string> */
    public const RSID = ['rsid', 'snp', 'marker', 'variant'];

    /** @var list<string> */
    public const GENOTYPE = ['genotype', 'result', 'call', 'alleles'];

    /** @var list<string> */
    private const GENE = ['gene', 'symbol'];

    /** The first allele pair in a cell, e.g. `A/G` in "GRCh38 genomic A/G; protein Val/Met". */
    private const ALLELE_PAIR = '/(?<![A-Za-z0-9*])(?:[ACGTDI] ?[\/|;] ?[ACGTDI]|[ACGTDI]{2})(?![A-Za-z0-9*])/';

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
        foreach ($this->rows($absolutePath) as $row) {
            if ($row !== null && $row->variant !== null) {
                yield $row->variant;
            }
        }
    }

    /**
     * Reads the whole file once. Null when it has more marker rows (rows read,
     * plus rows with an rsID that could not be read) than $limit.
     */
    public function scan(string $absolutePath, int $limit): ?MarkerScan
    {
        $candidates = [];
        $rows = 0;

        foreach ($this->rows($absolutePath) as $row) {
            $rows++;

            if ($row === null) {
                continue;
            }

            if (count($candidates) === $limit) {
                return null;
            }

            $candidates[] = $row;
        }

        return new MarkerScan($candidates, $rows);
    }

    /**
     * Every data row: a MarkerRow for one with a variant or an unread rsID, null
     * for one with neither (no rsID anywhere, an explicit no-call, or cells that
     * don't line up with the header).
     *
     * @return Generator<int, MarkerRow|null>
     */
    private function rows(string $absolutePath): Generator
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

            yield $this->row($line, $columns);
        }
    }

    /**
     * @param  array{rsid: int, genotype: int, gene: int|null, names: list<string>, prompt: list<int>}  $columns
     */
    private function row(string $line, array $columns): ?MarkerRow
    {
        $fields = Lines::csv($line);
        $genotype = $fields[$columns['genotype']] ?? '';

        if ($this->isNoCall($genotype)) {
            return null;
        }

        $variant = ParsedVariant::fromRaw(
            $fields[$columns['rsid']] ?? '',
            $this->genotypeIn($genotype),
            $columns['gene'] === null ? null : ($fields[$columns['gene']] ?? null),
        );

        $promptLine = $this->promptLine($fields, $columns);

        // An unread row with no rsID, or one whose cells don't line up with the header, is not a marker row.
        if ($variant === null && ($promptLine === null || preg_match('/\brs\d{1,10}\b/i', $line) !== 1)) {
            return null;
        }

        return new MarkerRow($variant, $promptLine);
    }

    /**
     * An explicit no-call such as `--` or `0/0`: the row has nothing to read,
     * locally or by the model. An empty cell is not one; the rsID row may say
     * more in another cell.
     */
    private function isNoCall(string $cell): bool
    {
        try {
            return $cell !== '' && Genotype::normalize($cell) === null;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * The cell itself when it is a clean genotype, otherwise its first allele
     * pair (genomic or plus strand, as reported first), otherwise nothing.
     */
    private function genotypeIn(string $cell): string
    {
        try {
            Genotype::normalize($cell);

            return $cell;
        } catch (InvalidArgumentException) {
            return preg_match(self::ALLELE_PAIR, $cell, $match) === 1 ? $match[0] : '';
        }
    }

    /**
     * The row as the model may see it: only the rsID, gene, genotype and marker
     * name cells, never notes or interpretation. Null when the cells don't line
     * up with the header (a quoted note split across lines), so a stray note
     * fragment can't pass as one of those cells.
     *
     * @param  list<string>  $fields
     * @param  array{rsid: int, genotype: int, gene: int|null, names: list<string>, prompt: list<int>}  $columns
     */
    private function promptLine(array $fields, array $columns): ?string
    {
        if (count($fields) !== count($columns['names'])) {
            return null;
        }

        $cells = [];

        foreach ($columns['prompt'] as $index) {
            $value = trim((string) preg_replace('/\s+/u', ' ', $fields[$index]));

            if ($value !== '') {
                $cells[] = $columns['names'][$index].': '.$value;
            }
        }

        return implode(' | ', $cells);
    }

    /**
     * @return array{rsid: int, genotype: int, gene: int|null, names: list<string>, prompt: list<int>}|null
     */
    private function columns(string $header): ?array
    {
        $names = Lines::csv($header);
        $tokens = array_map($this->tokens(...), $names);

        $rsid = $this->pick($tokens, self::RSID, []);
        $genotype = $this->pick($tokens, self::GENOTYPE, [$rsid]);

        if ($rsid === null || $genotype === null) {
            return null;
        }

        $gene = $this->pick($tokens, self::GENE, [$rsid, $genotype]);

        $prompt = array_keys(array_filter(
            $tokens,
            fn (array $parts, int $index): bool => in_array($index, [$rsid, $genotype, $gene], true) || array_intersect($parts, self::RSID) !== [],
            ARRAY_FILTER_USE_BOTH,
        ));

        return [
            'rsid' => $rsid,
            'genotype' => $genotype,
            'gene' => $gene,
            'names' => array_map(fn (string $name): string => (string) preg_replace('/\s+/u', ' ', $name), $names),
            'prompt' => $prompt,
        ];
    }

    /**
     * The first column, in alias priority and then file order, that one of the
     * aliases names and that isn't already taken.
     *
     * @param  list<list<string>>  $tokens
     * @param  list<string>  $aliases
     * @param  list<int|null>  $taken
     */
    private function pick(array $tokens, array $aliases, array $taken): ?int
    {
        foreach ($aliases as $alias) {
            foreach ($tokens as $index => $parts) {
                if (! in_array($index, $taken, true) && in_array($alias, $parts, true)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * "Gene / Locus" -> ["gene", "locus"]. Spaces don't split, so "Marker Type"
     * stays one token and never reads as a marker column.
     *
     * @return list<string>
     */
    private function tokens(string $name): array
    {
        $parts = preg_split('/[\/|;,()]+/', strtolower($name)) ?: [];

        return array_values(array_filter(array_map(
            fn (string $part): string => trim((string) preg_replace('/\s+/', ' ', $part)),
            $parts,
        ), fn (string $part): bool => $part !== ''));
    }
}
