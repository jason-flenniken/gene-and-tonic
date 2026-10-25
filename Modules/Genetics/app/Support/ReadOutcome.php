<?php

namespace Modules\Genetics\Support;

use Modules\Genetics\Enums\ParseMethod;
use Modules\Genetics\Support\Parsing\ParsedVariant;

/**
 * The markers read from a marker list or extracted file, one per rsID, and how
 * they were read. Skipped counts data rows that yielded no stored marker.
 */
final readonly class ReadOutcome
{
    /**
     * @param  list<ParsedVariant>  $variants
     */
    public function __construct(
        public array $variants,
        public ParseMethod $method,
        public int $localCount,
        public int $aiCount,
        public int $skippedCount,
    ) {}

    /**
     * @return array{parse_method: ParseMethod, local_count: int, ai_count: int, skipped_count: int}
     */
    public function counts(): array
    {
        return [
            'parse_method' => $this->method,
            'local_count' => $this->localCount,
            'ai_count' => $this->aiCount,
            'skipped_count' => $this->skippedCount,
        ];
    }
}
