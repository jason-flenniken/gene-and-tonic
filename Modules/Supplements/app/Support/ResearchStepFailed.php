<?php

namespace Modules\Supplements\Support;

use RuntimeException;

/**
 * A retryable pipeline failure. The message is readable and safe to show to the user.
 */
final class ResearchStepFailed extends RuntimeException {}
