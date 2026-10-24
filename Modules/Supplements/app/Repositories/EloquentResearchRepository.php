<?php

namespace Modules\Supplements\Repositories;

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

    public function hasActiveRun(Supplement $supplement): bool
    {
        return SupplementResearch::query()
            ->where('supplement_id', $supplement->getKey())
            ->whereIn('status', array_values(array_filter(
                ResearchStatus::cases(),
                fn (ResearchStatus $status): bool => $status->isActive(),
            )))
            ->exists();
    }
}
