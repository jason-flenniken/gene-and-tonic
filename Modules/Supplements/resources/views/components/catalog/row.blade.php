@props(['supplement', 'selected' => false])

@php
    $research = $supplement->research;
    $status = $research?->status;
    $stepLabels = ['queued' => 'Queued', 'searching' => 'Searching sources', 'analyzing' => 'Analyzing'];
    $isComplete = $status === \Modules\Supplements\Enums\ResearchStatus::Complete;
    $isFailed = $status === \Modules\Supplements\Enums\ResearchStatus::Failed;
@endphp

<div {{ $attributes->class([
    'flex items-center gap-2 rounded-card border-[1.5px] pr-3 transition-colors',
    'border-juniper bg-surface' => $selected,
    'border-transparent hover:bg-surface' => ! $selected,
]) }}>
    <a
        href="{{ route('supplements.show', $supplement) }}"
        x-on:click="if (window.matchMedia('(min-width: 768px)').matches) { $event.preventDefault(); $wire.selectSupplement({{ $supplement->id }}) }"
        @if ($selected) aria-current="true" @endif
        class="flex min-w-0 flex-1 items-center gap-3.5 rounded-card px-[18px] py-4 text-ink focus-visible:outline-2 focus-visible:outline-juniper"
    >
        <span class="flex min-w-0 flex-1 flex-col gap-[3px]">
            <span class="text-base font-semibold">{{ $supplement->name }}</span>

            @if ($status?->isActive())
                <x-ui.progress-steps :step="$status->step()" :label="$stepLabels[$status->value]" class="mt-0.5" />
            @elseif ($isFailed)
                <span class="text-[13px] text-bad-text">{{ $research->error ?? 'Research failed.' }}</span>
            @elseif ($isComplete)
                <span class="text-[13px] text-ink-muted">
                    {{ $supplement->effects_count }} {{ \Illuminate\Support\Str::plural('effect', $supplement->effects_count) }}
                    · {{ $supplement->studies_count }} {{ \Illuminate\Support\Str::plural('study', $supplement->studies_count) }}
                    · {{ $supplement->sources_count }} {{ \Illuminate\Support\Str::plural('source', $supplement->sources_count) }}
                    @foreach (app(\Modules\Shared\Contracts\PageActions::class)->for('supplement.card') as $cardView)
                        @include($cardView, ['supplementId' => $supplement->id])
                    @endforeach
                </span>
            @else
                <span class="text-[13px] text-ink-muted">No research yet</span>
            @endif
        </span>
        <x-icon.chevron-right class="size-4 shrink-0 {{ $selected ? 'text-juniper' : 'text-ink-subtle' }}" />
    </a>

    @if ($isFailed)
        <x-ui.button variant="secondary" wire:click="retry({{ $supplement->id }})" loading="retry" class="relative !h-9 shrink-0 !px-4 !text-[13px] after:absolute after:-inset-y-1 after:inset-x-0 after:content-['']">Retry</x-ui.button>
    @endif
</div>
