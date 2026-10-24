<?php

namespace Modules\Genetics\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Modules\Genetics\Actions\LookupVariant;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Throwable;

/**
 * Looks up one reference row. QueueLookups has already claimed the row (status LookingUp).
 */
class LookupGeneVariant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const GENERIC_ERROR = 'The lookup failed unexpectedly. Try again later.';

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 120;

    public function __construct(public readonly string $rsid) {}

    /**
     * A concurrent duplicate is dropped, not released. The lock must outlive the
     * job timeout but expire before retry_after makes the job reservable again.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->rsid))->dontRelease()->expireAfter($this->lockSeconds())];
    }

    public function lockSeconds(): int
    {
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 240);

        return max($this->timeout + 1, $retryAfter - 30);
    }

    public function handle(LookupVariant $lookup): void
    {
        $lookup->handle($this->rsid);
    }

    /** Never show raw exception messages: they can contain provider details. */
    public function failed(?Throwable $exception = null): void
    {
        GeneVariant::where('rsid', $this->rsid)
            ->where('status', LookupStatus::LookingUp)
            ->update(['status' => LookupStatus::Failed, 'error' => self::GENERIC_ERROR]);
    }
}
