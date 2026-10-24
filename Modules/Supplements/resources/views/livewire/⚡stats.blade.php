<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\SupplementRepository;

new class extends Component
{
    #[Computed]
    public function total(): int
    {
        return app(SupplementRepository::class)->total();
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return app(SupplementRepository::class)->countByStatus();
    }

    /** @return Collection<int, Supplement> */
    #[Computed]
    public function recent(): Collection
    {
        return app(SupplementRepository::class)->recentlyCompleted(5);
    }
};
?>

<flux:card class="space-y-4">
    <flux:heading size="lg">Supplements</flux:heading>

    <div data-stat="total">
        <div class="text-sm text-zinc-500">Total supplements</div>
        <div class="text-2xl font-semibold">{{ number_format($this->total) }}</div>
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (ResearchStatus::cases() as $status)
            <flux:badge :color="$status->color()" data-status="{{ $status->value }}">{{ $status->label() }}: {{ $this->counts[$status->value] ?? 0 }}</flux:badge>
        @endforeach
    </div>

    <div>
        <flux:heading>Recently completed</flux:heading>
        @if ($this->recent->isEmpty())
            <flux:text class="mt-2">No completed research yet.</flux:text>
        @else
            <ul class="mt-2 space-y-1">
                @foreach ($this->recent as $supplement)
                    <li wire:key="recent-{{ $supplement->id }}" class="flex justify-between gap-3 text-sm">
                        <a href="{{ route('supplements.show', $supplement->id) }}" class="font-medium hover:underline">{{ $supplement->name }}</a>
                        <span class="text-zinc-500">{{ $supplement->research?->completed_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</flux:card>
