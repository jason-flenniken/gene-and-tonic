<?php

namespace Modules\Supplements\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Modules\Supplements\Actions\ResearchSupplement;
use Modules\Supplements\Repositories\ResearchRepository;
use Modules\Supplements\Support\ResearchStepFailed;
use Throwable;

class RunSupplementResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 200;

    public function __construct(public readonly int $supplementId) {}

    /**
     * A concurrent duplicate is dropped (not released) so it can never reach the paid steps
     * or burn a retry attempt. A worker killed on timeout never releases the lock, so it must
     * expire after the job timeout but before retry_after makes the job reservable again;
     * otherwise the retry would be dropped and the run left stuck in an active status.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->supplementId))->dontRelease()->expireAfter($this->lockSeconds())];
    }

    public function lockSeconds(): int
    {
        $retryAfter = (int) config('queue.connections.'.config('queue.default').'.retry_after', 240);

        return max($this->timeout + 1, $retryAfter - 30);
    }

    public function handle(ResearchSupplement $research, ResearchRepository $runs): void
    {
        try {
            $research->handle($this->supplementId);
        } catch (ResearchStepFailed $e) {
            if ($this->canRetry()) {
                $this->release($this->backoff);

                return;
            }

            $runs->markFailed($this->supplementId, $e->getMessage());
        }
    }

    /** Never show raw exception messages: they can contain provider details. */
    public function failed(?Throwable $exception = null): void
    {
        app(ResearchRepository::class)->markFailed($this->supplementId, 'Research failed unexpectedly');
    }

    /** Sync and unqueued runs cannot be released, so they fail on the first error. */
    private function canRetry(): bool
    {
        return $this->job !== null
            && ! $this->job instanceof SyncJob
            && $this->attempts() < $this->tries;
    }
}
