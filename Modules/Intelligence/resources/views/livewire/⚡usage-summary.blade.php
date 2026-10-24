<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Intelligence\Repositories\AiRequestRepository;
use Modules\Intelligence\Repositories\UsageSummary;

new class extends Component
{
    #[Computed]
    public function summary(): UsageSummary
    {
        return app(AiRequestRepository::class)->summary();
    }
};
?>

<flux:card class="space-y-4">
    <flux:heading size="lg">AI usage</flux:heading>

    <dl class="grid grid-cols-2 gap-4">
        <div data-stat="calls">
            <dt class="text-sm text-zinc-500">Calls</dt>
            <dd class="text-2xl font-semibold">{{ number_format($this->summary->calls) }}</dd>
            <dd class="text-xs text-zinc-500">{{ number_format($this->summary->failedCalls) }} failed</dd>
        </div>
        <div data-stat="cost">
            <dt class="text-sm text-zinc-500">Estimated cost</dt>
            <dd class="text-2xl font-semibold">${{ number_format($this->summary->estimatedCostUsd, 4) }}</dd>
        </div>
        <div data-stat="input-tokens">
            <dt class="text-sm text-zinc-500">Input tokens</dt>
            <dd class="text-lg font-medium">{{ number_format($this->summary->inputTokens) }}</dd>
        </div>
        <div data-stat="output-tokens">
            <dt class="text-sm text-zinc-500">Output tokens</dt>
            <dd class="text-lg font-medium">{{ number_format($this->summary->outputTokens) }}</dd>
        </div>
        <div data-stat="calls-24h">
            <dt class="text-sm text-zinc-500">Calls, last 24h</dt>
            <dd class="text-lg font-medium">{{ number_format($this->summary->callsLast24h) }}</dd>
        </div>
        <div data-stat="cost-24h">
            <dt class="text-sm text-zinc-500">Cost, last 24h</dt>
            <dd class="text-lg font-medium">${{ number_format($this->summary->costLast24hUsd, 4) }}</dd>
        </div>
    </dl>
</flux:card>
