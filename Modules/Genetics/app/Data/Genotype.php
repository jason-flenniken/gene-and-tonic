<?php

namespace Modules\Genetics\Data;

use InvalidArgumentException;

/**
 * Pure helpers for genotype strings: one or two alleles from A C G T D I,
 * sorted, e.g. "CT". Never put a genotype in an exception message or a log.
 */
final class Genotype
{
    public static function normalize(string $raw): ?string
    {
        $stripped = strtoupper(str_replace(['/', ';', '|', ' '], '', $raw));

        if (in_array($stripped, ['', '-', '--', '0', '00'], true)) {
            return null;
        }

        if (preg_match('/^[ACGTDI]{1,2}$/', $stripped) !== 1) {
            throw new InvalidArgumentException('Invalid genotype.');
        }

        return self::sorted($stripped);
    }

    public static function complement(string $alleles): string
    {
        return strtr($alleles, 'ACGT', 'TGCA');
    }

    /**
     * Flips a genotype read off the gene strand onto the plus strand when its
     * alleles don't fit the reference row but their complements do. A/T and
     * C/G SNPs read the same on both strands, so they are left as given.
     */
    public static function toPlusStrand(string $genotype, ?string $ref, ?string $risk): string
    {
        $known = self::known($ref, $risk);

        if ($known === [] || self::isStrandAmbiguous($known)) {
            return $genotype;
        }

        $complement = self::complement($genotype);

        if (! self::fits($genotype, $known) && self::fits($complement, $known)) {
            return self::sorted($complement);
        }

        return $genotype;
    }

    /**
     * How many copies (0-2) of $allele the genotype carries, with both the
     * allele and the genotype resolved to the plus strand first.
     */
    public static function copiesOf(string $genotype, string $allele, ?string $ref, ?string $risk): int
    {
        $known = self::known($ref, $risk);

        if (! in_array($allele, $known, true) && in_array(self::complement($allele), $known, true)) {
            $allele = self::complement($allele);
        }

        return substr_count(self::toPlusStrand($genotype, $ref, $risk), $allele);
    }

    /** @return list<string> */
    private static function known(?string $ref, ?string $risk): array
    {
        return array_values(array_filter([$ref, $risk], fn (?string $allele): bool => $allele !== null && $allele !== ''));
    }

    /** @param list<string> $known */
    private static function isStrandAmbiguous(array $known): bool
    {
        return count($known) === 2 && self::complement($known[0]) === $known[1];
    }

    /** @param list<string> $known */
    private static function fits(string $genotype, array $known): bool
    {
        return array_diff(str_split($genotype), $known) === [];
    }

    private static function sorted(string $alleles): string
    {
        $split = str_split($alleles);
        sort($split);

        return implode('', $split);
    }
}
