<?php

namespace Modules\Supplements\Support;

use RuntimeException;

/**
 * The model's answer does not have the expected shape. Retrying will not help.
 */
final class UnusableResearchOutput extends RuntimeException {}
