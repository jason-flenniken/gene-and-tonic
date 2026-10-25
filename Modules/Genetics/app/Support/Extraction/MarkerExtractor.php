<?php

namespace Modules\Genetics\Support\Extraction;

/**
 * Pulls markers out of a file no deterministic parser recognises (and every pdf).
 */
interface MarkerExtractor
{
    public function extract(string $absolutePath, string $extension): Extraction;
}
