<?php

namespace Modules\Genetics\Support\Extraction;

use Smalot\PdfParser\Parser;

/**
 * Pdf text through smalot/pdfparser; txt and csv are read up to a byte cap.
 */
final class LocalTextExtractor implements TextExtractor
{
    private const MAX_PLAIN_BYTES = 2_000_000;

    public function extract(string $absolutePath, string $extension): string
    {
        if (strtolower($extension) === 'pdf') {
            return (new Parser)->parseFile($absolutePath)->getText();
        }

        $text = file_get_contents($absolutePath, false, null, 0, self::MAX_PLAIN_BYTES);

        return $text === false ? '' : $text;
    }
}
