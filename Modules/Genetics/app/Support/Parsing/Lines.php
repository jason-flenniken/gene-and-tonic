<?php

namespace Modules\Genetics\Support\Parsing;

use Generator;
use RuntimeException;

/**
 * Streams a text file one line at a time, so a 25 MB export is never read
 * into memory whole.
 */
final class Lines
{
    /**
     * @return Generator<int, string>
     */
    public static function read(string $absolutePath): Generator
    {
        $handle = @fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be opened.');
        }

        try {
            $first = true;

            while (($line = fgets($handle)) !== false) {
                if ($first) {
                    $line = (string) preg_replace('/^\xEF\xBB\xBF/', '', $line);
                    $first = false;
                }

                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Lines that carry data: not blank and not a `#` comment.
     *
     * @param  iterable<string>  $lines
     * @return Generator<int, string>
     */
    public static function content(iterable $lines): Generator
    {
        foreach ($lines as $line) {
            if (trim($line) !== '' && ! str_starts_with(ltrim($line), '#')) {
                yield $line;
            }
        }
    }

    /**
     * @param  list<string>  $headLines
     */
    public static function firstContent(array $headLines): ?string
    {
        foreach (self::content($headLines) as $line) {
            return $line;
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function csv(string $line): array
    {
        return array_map(fn (?string $field): string => trim((string) $field), str_getcsv($line, ',', '"', ''));
    }
}
