<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Intelligence\Contracts\ProfileRegistry;
use Modules\Intelligence\Enums\UsageWindow;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Repositories\AiRequestRepository;
use Modules\Intelligence\Repositories\ProfileCost;
use Modules\Intelligence\Repositories\UsageSummary;

new #[Title('AI usage')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $window = 'all';

    public function updatedWindow(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function usageWindow(): UsageWindow
    {
        return UsageWindow::tryFrom($this->window) ?? UsageWindow::All;
    }

    #[Computed]
    public function summary(): UsageSummary
    {
        return app(AiRequestRepository::class)->summary($this->usageWindow);
    }

    /** @return list<ProfileCost> */
    #[Computed]
    public function costByTask(): array
    {
        return app(AiRequestRepository::class)->costByProfile($this->usageWindow);
    }

    /** @return LengthAwarePaginator<int, AiRequest> */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return app(AiRequestRepository::class)->paginateLatest(20, $this->usageWindow);
    }

    public function labelFor(?string $profile, string $operation): string
    {
        return app(ProfileRegistry::class)->labelFor($profile, $operation);
    }
};
?>

<x-ui.panel>
    <x-ui.page-header title="AI usage" subtitle="Every model call goes through one gateway and is logged with its tokens and cost.">
        <x-slot:actions>
            <x-ui.segmented aria-label="Time window">
                @foreach (UsageWindow::cases() as $option)
                    <x-ui.segmented.item
                        wire:key="window-{{ $option->value }}"
                        :active="$this->usageWindow === $option"
                        wire:click="$set('window', '{{ $option->value }}')"
                    >{{ $option->label() }}</x-ui.segmented.item>
                @endforeach
            </x-ui.segmented>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        <x-ui.stat label="Calls" :value="number_format($this->summary->calls)" data-stat="calls">
            @if ($this->summary->failedCalls > 0)
                <x-slot:caption><span class="text-bad-text">{{ number_format($this->summary->failedCalls) }} failed</span></x-slot:caption>
            @endif
        </x-ui.stat>
        <x-ui.stat
            label="Estimated cost"
            :value="'$'.number_format($this->summary->estimatedCostUsd, 4)"
            :caption="$this->summary->calls > 0 ? 'About $'.number_format($this->summary->estimatedCostUsd / $this->summary->calls, 4).' a call' : null"
            data-stat="cost"
        />
        <x-ui.stat label="Input tokens" :value="number_format($this->summary->inputTokens)" data-stat="input-tokens" />
        <x-ui.stat label="Output tokens" :value="number_format($this->summary->outputTokens)" data-stat="output-tokens" />
    </div>

    <section class="flex flex-col gap-3.5" aria-labelledby="cost-by-task">
        <h2 id="cost-by-task" class="text-[18px] font-bold text-ink">Cost by task</h2>
        @php($maxCost = max(array_map(fn ($task) => $task->cost, $this->costByTask) ?: [0.0]))
        @forelse ($this->costByTask as $task)
            @php($width = $maxCost > 0 ? max(2, round($task->cost / $maxCost * 100, 1)) : 2)
            <div wire:key="task-{{ $task->profile ?? 'none' }}-{{ $task->operation }}" data-task-row class="grid items-center gap-x-4 gap-y-1 [grid-template-columns:minmax(150px,220px)_minmax(0,1fr)_100px]">
                <div class="flex flex-col gap-0.5">
                    <span class="text-[15px] font-semibold text-ink">{{ $this->labelFor($task->profile, $task->operation).($task->operation === 'search' ? ' · '.number_format($task->credits).' credits' : '') }}</span>
                    <span class="text-[13px] text-ink-subtle">{{ number_format($task->calls).' '.Str::plural('call', $task->calls).($task->failed > 0 ? ' · '.number_format($task->failed).' failed' : '') }}</span>
                </div>
                <span class="flex h-3 rounded-md bg-surface-sunken"><span class="block h-3 rounded-md bg-juniper" style="width: {{ $width }}%"></span></span>
                <span class="tabular text-right text-[15px] font-semibold text-ink">${{ number_format($task->cost, 4) }}</span>
            </div>
        @empty
            <p class="text-[14px] text-ink-muted">No AI calls recorded yet.</p>
        @endforelse
    </section>

    <section class="flex flex-col gap-3" aria-labelledby="recent-calls">
        <h2 id="recent-calls" class="text-[18px] font-bold text-ink">Recent calls</h2>
        @if ($this->rows->isEmpty())
            <p class="text-[14px] text-ink-muted">No AI calls recorded yet.</p>
        @else
            <div class="overflow-x-auto rounded-card border border-line">
                <table class="w-full min-w-[720px] border-collapse text-[14px]">
                    <thead>
                        <tr class="bg-surface-muted text-[12px] font-bold tracking-[0.06em] text-ink-muted uppercase">
                            <th class="px-4 py-3 text-left">Time</th>
                            <th class="px-4 py-3 text-left">Task</th>
                            <th class="px-4 py-3 text-right">Tokens in / out</th>
                            <th class="px-4 py-3 text-right">Latency</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            <tr wire:key="ai-request-{{ $row->id }}" class="border-t border-line-soft">
                                <td class="px-4 py-3.5 whitespace-nowrap text-ink-muted" title="{{ $row->created_at }}">{{ $row->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3.5">
                                    <span class="font-semibold text-ink">{{ $this->labelFor($row->profile, $row->operation) }}</span>
                                    <span class="block text-[13px] text-ink-subtle">{{ $row->provider }}{{ $row->model ? ' / '.$row->model : '' }}{{ $row->effort ? ' · '.$row->effort : '' }}</span>
                                </td>
                                <td class="tabular px-4 py-3.5 text-right whitespace-nowrap">
                                    @if ($row->operation === 'search')
                                        {{ number_format((int) $row->credits) }} credits
                                    @else
                                        {{ number_format($row->input_tokens) }} / {{ number_format($row->output_tokens) }}
                                    @endif
                                </td>
                                <td class="tabular px-4 py-3.5 text-right whitespace-nowrap">{{ number_format($row->latency_ms) }} ms</td>
                                <td class="tabular px-4 py-3.5 text-right whitespace-nowrap">${{ number_format((float) $row->estimated_cost_usd, 4) }}</td>
                                <td class="px-4 py-3.5">
                                    <x-ui.status :tone="$row->status === 'success' ? 'ok' : 'bad'" :label="$row->status === 'success' ? 'OK' : 'FAILED'" />
                                    @if ($row->status === 'failed' && $row->error)
                                        <span class="mt-1 block max-w-xs truncate text-[13px] text-ink-subtle" title="{{ $row->error }}">{{ Str::limit($row->error, 80) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div>{{ $this->rows->links() }}</div>
        @endif
    </section>
</x-ui.panel>
