<?php

namespace Modules\Genetics\Support\Parsing;

/**
 * A RawExport is a vendor's whole-genome dump: only rsIDs already in the
 * reference table are kept. A MarkerList is a short, curated list: every row
 * is kept and unknown rsIDs get pending reference rows.
 */
enum ParsedKind
{
    case RawExport;
    case MarkerList;
}
