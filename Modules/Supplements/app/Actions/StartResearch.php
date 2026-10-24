<?php

namespace Modules\Supplements\Actions;

use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\ResearchRepository;

class StartResearch
{
    public function __construct(private readonly ResearchRepository $research) {}

    /**
     * Queue a research run unless one is already active.
     */
    public function handle(Supplement $supplement): bool
    {
        if ($this->research->hasActiveRun($supplement)) {
            return false;
        }

        $this->research->markQueued($supplement);
        RunSupplementResearch::dispatch($supplement->getKey());

        return true;
    }
}
