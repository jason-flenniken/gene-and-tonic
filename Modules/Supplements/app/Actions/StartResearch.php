<?php

namespace Modules\Supplements\Actions;

use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\ResearchRepository;

class StartResearch
{
    public function __construct(private readonly ResearchRepository $research) {}

    /**
     * Queue a research run only if this call atomically claimed the run.
     */
    public function handle(Supplement $supplement): bool
    {
        if (! $this->research->claimRun($supplement)) {
            return false;
        }

        RunSupplementResearch::dispatch($supplement->getKey());

        return true;
    }
}
