<?php

namespace Modules\Supplements\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

class EloquentResearchRepository implements ResearchRepository
{
    public function markQueued(Supplement $supplement): SupplementResearch
    {
        return SupplementResearch::query()->updateOrCreate(
            ['supplement_id' => $supplement->getKey()],
            [
                'status' => ResearchStatus::Queued,
                'error' => null,
                'queued_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ],
        );
    }

    public function claimRun(Supplement $supplement): bool
    {
        $claimed = SupplementResearch::query()
            ->where('supplement_id', $supplement->getKey())
            ->whereNotIn('status', $this->activeStatuses())
            ->update([
                'status' => ResearchStatus::Queued,
                'error' => null,
                'queued_at' => now(),
                'started_at' => null,
                'completed_at' => null,
            ]) === 1;

        if ($claimed) {
            return true;
        }

        // Either a run is active (lost the claim) or no row exists yet. Create
        // it as queued; the unique supplement_id index decides a creation race.
        try {
            SupplementResearch::query()->create([
                'supplement_id' => $supplement->getKey(),
                'status' => ResearchStatus::Queued,
                'queued_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function hasActiveRun(Supplement $supplement): bool
    {
        return SupplementResearch::query()
            ->where('supplement_id', $supplement->getKey())
            ->whereIn('status', $this->activeStatuses())
            ->exists();
    }

    /** @return list<ResearchStatus> */
    private function activeStatuses(): array
    {
        return array_values(array_filter(
            ResearchStatus::cases(),
            fn (ResearchStatus $status): bool => $status->isActive(),
        ));
    }
}
