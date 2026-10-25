<?php

namespace Modules\Genetics\Support\Extraction;

use Modules\Genetics\Support\Parsing\ParsedVariant;

/**
 * What the model returned for a set of marker lines (validated, one per rsID),
 * the lines it was actually sent, whether genetics.extract.max_chunks cut
 * some lines off, and how many lines that left unsent.
 */
final readonly class Extraction
{
    /**
     * @param  list<ParsedVariant>  $variants
     * @param  list<string>  $sent
     */
    public function __construct(
        public array $variants,
        public array $sent,
        public bool $truncated,
        public int $unsentCount = 0,
    ) {}

    /**
     * The variants whose rsID appears in a line that was sent, so the model
     * can't add a marker the file didn't list.
     *
     * @return list<ParsedVariant>
     */
    public function listedVariants(): array
    {
        preg_match_all('/\brs\d{1,10}\b/i', implode("\n", $this->sent), $matches);
        $listed = array_flip(array_map(strtolower(...), $matches[0]));

        return array_values(array_filter($this->variants, fn (ParsedVariant $variant): bool => isset($listed[$variant->rsid])));
    }
}
