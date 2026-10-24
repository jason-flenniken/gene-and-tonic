<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Repositories\AiRequestRepository;

new class extends Component
{
    use WithPagination;

    /** @return LengthAwarePaginator<int, AiRequest> */
    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return app(AiRequestRepository::class)->paginateLatest(20);
    }
};
?>

<div class="space-y-6">
    <livewire:intelligence::usage-summary />

    <flux:card>
        @if ($this->rows->isEmpty())
            <flux:text>No AI calls recorded yet.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pe-4 font-medium">Time</th>
                            <th class="py-2 pe-4 font-medium">Operation</th>
                            <th class="py-2 pe-4 font-medium">Profile</th>
                            <th class="py-2 pe-4 font-medium">Provider / model</th>
                            <th class="py-2 pe-4 font-medium">Effort</th>
                            <th class="py-2 pe-4 font-medium">Tokens</th>
                            <th class="py-2 pe-4 font-medium">Latency</th>
                            <th class="py-2 pe-4 font-medium">Cost</th>
                            <th class="py-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rows as $row)
                            <tr wire:key="ai-request-{{ $row->id }}">
                                <td class="py-2 pe-4 whitespace-nowrap" title="{{ $row->created_at }}">{{ $row->created_at?->diffForHumans() }}</td>
                                <td class="py-2 pe-4"><flux:badge size="sm">{{ $row->operation }}</flux:badge></td>
                                <td class="py-2 pe-4">{{ $row->profile ?? '—' }}</td>
                                <td class="py-2 pe-4">{{ $row->provider }}{{ $row->model ? ' / '.$row->model : '' }}</td>
                                <td class="py-2 pe-4">{{ $row->effort ?? '—' }}</td>
                                <td class="py-2 pe-4 whitespace-nowrap">
                                    @if ($row->operation === 'search')
                                        {{ number_format((int) $row->credits) }} credits
                                    @else
                                        {{ number_format($row->input_tokens) }} / {{ number_format($row->output_tokens) }}
                                    @endif
                                </td>
                                <td class="py-2 pe-4 whitespace-nowrap">{{ number_format($row->latency_ms) }} ms</td>
                                <td class="py-2 pe-4 whitespace-nowrap">${{ number_format((float) $row->estimated_cost_usd, 4) }}</td>
                                <td class="py-2">
                                    <flux:badge size="sm" :color="$row->status === 'success' ? 'green' : 'red'">{{ $row->status }}</flux:badge>
                                    @if ($row->status === 'failed' && $row->error)
                                        <div class="max-w-xs truncate text-xs text-zinc-500" title="{{ $row->error }}">{{ Str::limit($row->error, 80) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $this->rows->links() }}</div>
        @endif
    </flux:card>
</div>
