<?php

namespace Modules\Supplements\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunSupplementResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 15;

    public int $timeout = 180;

    public function __construct(public readonly int $supplementId) {}

    /**
     * Run the research pipeline for the supplement.
     *
     * The research-pipeline task implements this by calling ResearchSupplement.
     * Until then it is a deliberate no-op.
     */
    public function handle(): void
    {
        //
    }
}
