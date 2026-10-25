<?php

namespace Modules\Intelligence\Repositories;

/**
 * Internal to Intelligence: aggregate AI usage totals.
 */
final readonly class UsageSummary
{
    public function __construct(
        public int $calls,
        public int $failedCalls,
        public int $inputTokens,
        public int $outputTokens,
        public float $estimatedCostUsd,
    ) {}
}
