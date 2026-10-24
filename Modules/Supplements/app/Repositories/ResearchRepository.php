<?php

namespace Modules\Supplements\Repositories;

use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

interface ResearchRepository
{
    /** Create or reset the supplement's research row to Queued. */
    public function markQueued(Supplement $supplement): SupplementResearch;

    public function hasActiveRun(Supplement $supplement): bool;
}
