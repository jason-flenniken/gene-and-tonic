<?php

namespace Modules\Genetics\Actions;

use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Jobs\LookupGeneVariant;
use Modules\Genetics\Models\GeneVariant;

class QueueLookups
{
    /**
     * Claims each pending or failed reference row (an atomic status flip) and dispatches one
     * lookup job per claimed row, up to $cap. A row already being looked up is skipped, so a
     * double click or a second upload never queues a duplicate paid lookup.
     *
     * @param  list<string>  $rsids
     * @return int how many lookups were queued
     */
    public function handle(array $rsids, int $cap): int
    {
        $queued = 0;

        foreach ($rsids as $rsid) {
            if ($queued >= $cap) {
                break;
            }

            $claimed = GeneVariant::query()
                ->where('rsid', $rsid)
                ->whereIn('status', [LookupStatus::Pending, LookupStatus::Failed])
                ->update(['status' => LookupStatus::LookingUp, 'error' => null]);

            if ($claimed === 1) {
                LookupGeneVariant::dispatch($rsid);
                $queued++;
            }
        }

        return $queued;
    }
}
