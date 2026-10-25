<?php

namespace Modules\Genetics\Support;

/**
 * Outcome of queueing every pending lookup for one user.
 */
final readonly class LookUpAllResult
{
    public function __construct(
        public int $queued,
        public bool $limited = false,
        public bool $nothingPending = false,
    ) {}
}
