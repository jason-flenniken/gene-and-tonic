<?php

namespace Modules\Genetics\Support\Extraction;

/**
 * Reads the plain text of an uploaded file, locally. Nothing leaves the server.
 */
interface TextExtractor
{
    public function extract(string $absolutePath, string $extension): string;
}
