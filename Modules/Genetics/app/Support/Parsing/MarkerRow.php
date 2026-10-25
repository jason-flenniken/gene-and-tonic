<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * One marker-list row with an rsID: the variant read locally (null when its
 * cells couldn't be read) and the row's allowed cells as a line for the model
 * (null when the row can't be offered to it).
 */
final readonly class MarkerRow
{
    public function __construct(
        public ?ParsedVariant $variant,
        public ?string $promptLine,
    ) {}
}
