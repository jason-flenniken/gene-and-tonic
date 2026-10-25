<?php

namespace Modules\Genetics\Actions;

use Illuminate\Support\Facades\RateLimiter;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;
use Modules\Genetics\Support\LookUpAllResult;

class LookUpAll
{
    private const MAX_ATTEMPTS = 3;

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly QueueLookups $queueLookups) {}

    /**
     * Queues lookups for this user's own Pending and Failed variants, up to genetics.lookup_cap.
     * Rows already being looked up are skipped by QueueLookups.
     */
    public function handle(int $userId): LookUpAllResult
    {
        $key = 'genetics-lookup-all:'.$userId;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return new LookUpAllResult(queued: 0, limited: true);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        $rsids = array_values(array_map('strval', GeneVariant::query()
            ->whereIn('status', [LookupStatus::Pending, LookupStatus::Failed])
            ->whereIn('rsid', UserVariant::query()->where('user_id', $userId)->select('rsid'))
            ->orderBy('rsid')
            ->pluck('rsid')
            ->all()));

        if ($rsids === []) {
            return new LookUpAllResult(queued: 0, nothingPending: true);
        }

        return new LookUpAllResult(queued: $this->queueLookups->handle($rsids, (int) config('genetics.lookup_cap')));
    }
}
