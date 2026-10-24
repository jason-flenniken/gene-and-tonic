<?php

namespace Modules\Supplements\Console;

use Illuminate\Console\Command;
use Modules\Supplements\Repositories\ResearchRepository;

class ReapStaleResearch extends Command
{
    protected $signature = 'supplements:reap-stale';

    protected $description = 'Fail research runs that have been active for too long';

    public function handle(ResearchRepository $research): int
    {
        $minutes = (int) config('supplements.reap_after_minutes', 15);
        $count = $research->failStaleRuns(now()->subMinutes($minutes), 'Timed out, try again.');

        $this->info("Failed {$count} stale research run(s).");

        return self::SUCCESS;
    }
}
