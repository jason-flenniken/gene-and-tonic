<?php

namespace Modules\Supplements\Repositories;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;
use Modules\Supplements\Support\ResearchFindings;

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

    public function markSearching(int $supplementId): void
    {
        SupplementResearch::query()
            ->where('supplement_id', $supplementId)
            ->update(['status' => ResearchStatus::Searching, 'error' => null, 'started_at' => now()]);
    }

    public function markAnalyzing(int $supplementId): void
    {
        SupplementResearch::query()
            ->where('supplement_id', $supplementId)
            ->update(['status' => ResearchStatus::Analyzing]);
    }

    public function markFailed(int $supplementId, string $reason): void
    {
        SupplementResearch::query()
            ->where('supplement_id', $supplementId)
            ->where('status', '!=', ResearchStatus::Failed)
            ->update(['status' => ResearchStatus::Failed, 'error' => mb_substr($reason, 0, 500)]);
    }

    public function replaceFindings(int $supplementId, ResearchFindings $findings): void
    {
        DB::transaction(function () use ($supplementId, $findings): void {
            $research = SupplementResearch::query()->where('supplement_id', $supplementId)->first();

            if ($research === null) {
                return;
            }

            $research->sources()->delete();
            $research->effects()->delete();
            $research->mechanisms()->delete();
            $research->studies()->delete();

            $research->sources()->createMany($findings->sources);
            $research->effects()->createMany($findings->effects);
            $research->mechanisms()->createMany($findings->mechanisms);
            $research->studies()->createMany($findings->studies);

            $research->update([
                'summary' => $findings->summary,
                'safety_notes' => $findings->safetyNotes,
                'status' => ResearchStatus::Complete,
                'error' => null,
                'completed_at' => now(),
            ]);
        });
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
