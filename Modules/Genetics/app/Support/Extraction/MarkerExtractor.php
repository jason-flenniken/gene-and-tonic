<?php

namespace Modules\Genetics\Support\Extraction;

use Modules\Genetics\Support\Parsing\ParsedVariant;

/**
 * Pulls markers out of a file no deterministic parser recognises (and every pdf).
 */
interface MarkerExtractor
{
    /**
     * @return list<ParsedVariant>
     */
    public function extract(string $absolutePath, string $extension): array;
}
