<?php

namespace Modules\Supplements\Repositories;

use Carbon\CarbonInterface;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Support\ResearchFindings;

interface ResearchRepository
{
    /** Create or reset the supplement's research row to Queued. */
    public function markQueued(Supplement $supplement): SupplementResearch;

    /**
     * Atomically move the supplement's research to Queued. Returns true only
     * for the single caller that won the claim (no run was active).
     */
    public function claimRun(Supplement $supplement): bool;

    /** No-ops when the supplement (or its research row) no longer exists. */
    public function markSearching(int $supplementId): void;

    public function markAnalyzing(int $supplementId): void;

    /** Keeps the first reason if the run already failed. The reason is truncated to 500 characters. */
    public function markFailed(int $supplementId, string $reason): void;

    /** Fail active runs that started (or, if never started, were queued) before the cutoff. Returns the number failed. */
    public function failStaleRuns(CarbonInterface $before, string $reason): int;

    /** Replace all previous findings and mark the run Complete, in one transaction. */
    public function replaceFindings(int $supplementId, ResearchFindings $findings): void;
}
