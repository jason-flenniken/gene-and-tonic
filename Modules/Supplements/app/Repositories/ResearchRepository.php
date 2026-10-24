<?php

namespace Modules\Supplements\Repositories;

use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

interface ResearchRepository
{
    /** Create or reset the supplement's research row to Queued. */
    public function markQueued(Supplement $supplement): SupplementResearch;

    /**
     * Atomically move the supplement's research to Queued. Returns true only
     * for the single caller that won the claim (no run was active).
     */
    public function claimRun(Supplement $supplement): bool;

    /** Advisory only (e.g. UI); never use it to decide whether to dispatch. */
    public function hasActiveRun(Supplement $supplement): bool;
}
