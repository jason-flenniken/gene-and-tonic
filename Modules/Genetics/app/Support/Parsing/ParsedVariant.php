<?php

namespace Modules\Genetics\Support\Parsing;

use InvalidArgumentException;
use Modules\Genetics\Data\Genotype;

/**
 * One validated row from an upload: lowercase rsID, normalized genotype and an
 * optional uppercase gene symbol.
 */
final readonly class ParsedVariant
{
    public function __construct(
        public string $rsid,
        public string $genotype,
        public ?string $gene,
    ) {}

    /**
     * Returns null for a row that isn't a usable marker: a bad rsID (including
     * vendor-internal ids such as i7000123), a no-call or an invalid genotype.
     * A bad gene symbol is dropped rather than rejecting the row.
     */
    public static function fromRaw(string $rsid, string $genotype, ?string $gene): ?self
    {
        $rsid = strtolower(trim($rsid));

        if (preg_match('/^rs\d{1,10}$/', $rsid) !== 1) {
            return null;
        }

        try {
            $normalized = Genotype::normalize(trim($genotype));
        } catch (InvalidArgumentException) {
            return null;
        }

        if ($normalized === null) {
            return null;
        }

        $gene = $gene === null ? '' : strtoupper(trim($gene));

        return new self($rsid, $normalized, preg_match('/^[A-Z0-9-]{1,15}$/', $gene) === 1 ? $gene : null);
    }
}
