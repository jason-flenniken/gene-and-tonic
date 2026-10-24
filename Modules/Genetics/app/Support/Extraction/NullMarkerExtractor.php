<?php

namespace Modules\Genetics\Support\Extraction;

/**
 * Finds nothing. Bound until AI marker extraction exists, so an unrecognised
 * file fails with "No genetic markers found in this file."
 */
final class NullMarkerExtractor implements MarkerExtractor
{
    public function extract(string $absolutePath, string $extension): array
    {
        return [];
    }
}
