<?php

namespace Modules\Genetics\Support;

use RuntimeException;

/**
 * The model's answer cannot be stored. Retrying will not help.
 */
final class UnusableLookupOutput extends RuntimeException {}
