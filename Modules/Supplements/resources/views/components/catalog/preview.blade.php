@props(['supplement', 'research' => null, 'effects' => []])

@php
    $status = $research?->status;
    $isComplete = $status === \Modules\Supplements\Enums\ResearchStatus::Complete;
    $completedAt = $research?->completedAt;
    $researched = $completedAt === null ? null : ($completedAt->isToday() ? 'today, '.$completedAt->format('g:i A') : $completedAt->format('M j, Y'));
@endphp

<div class="flex flex-col gap-3.5 px-7 py-6" data-panel="supplement-preview">
    <div class="flex items-center justify-between gap-3">
        <span class="text-xs font-bold tracking-[0.08em] text-ink-muted">TOP EFFECTS IN <span class="text-juniper">{{ mb_strtoupper($supplement->name) }}</span></span>
        @if ($isComplete && $researched)
            <span class="text-xs text-ink-subtle">Researched {{ $researched }}</span>
        @endif
    </div>

    @if ($isComplete)
        @forelse ($effects as $effect)
            <div wire:key="effect-{{ $supplement->id }}-{{ $loop->index }}" class="flex items-center gap-3.5 rounded-card border border-line px-4 py-3.5">
                <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-juniper-tint text-[13px] font-bold text-juniper">{{ $loop->iteration }}</span>
                <span class="flex min-w-0 flex-1 flex-col items-start gap-1.5">
                    <span class="text-[15px] font-semibold">{{ $effect->title ?: $effect->claim }}</span>
                    <x-ui.grade :grade="$effect->evidenceGrade->value" />
                </span>
            </div>
        @empty
            <p class="text-[15px] text-ink-muted">No effects found in the research yet.</p>
        @endforelse

        @foreach (app(\Modules\Shared\Contracts\PageActions::class)->for('supplement.preview') as $previewView)
            @include($previewView, ['supplementId' => $supplement->id])
        @endforeach
    @elseif ($status?->isActive())
        <p class="text-[15px] text-ink-muted">Research is {{ strtolower($status->label()) }}. Effects appear here when it finishes.</p>
    @elseif ($status === \Modules\Supplements\Enums\ResearchStatus::Failed)
        <p class="text-[15px] text-bad-text">{{ $research->error ?? 'Research failed.' }}</p>
    @else
        <p class="text-[15px] text-ink-muted">No research yet.</p>
    @endif

    <div class="mt-1 flex flex-wrap items-center gap-2.5">
        @if ($isComplete)
            <x-ui.button :href="route('supplements.show', $supplement)" class="h-12 flex-[1_1_200px]">Open {{ $supplement->name }}</x-ui.button>
        @endif
        @if ($status && ! $status->isActive())
            <button type="button" wire:click="retry({{ $supplement->id }})" class="h-12 rounded-full bg-surface-sunken px-5 text-[15px] font-semibold text-ink hover:bg-line">{{ $status === \Modules\Supplements\Enums\ResearchStatus::Failed ? 'Retry' : 'Re-run' }}</button>
        @endif
        @if ($status)
            @foreach (app(\Modules\Shared\Contracts\PageActions::class)->for('supplement.header') as $headerView)
                @include($headerView, ['supplementId' => $supplement->id, 'status' => $status])
            @endforeach
        @endif
        <flux:modal.trigger name="delete-supplement">
            <button type="button" wire:click="confirmDelete({{ $supplement->id }})" class="h-12 rounded-full px-5 text-[15px] font-semibold text-bad-text hover:bg-surface-sunken">Delete</button>
        </flux:modal.trigger>
    </div>
</div>
