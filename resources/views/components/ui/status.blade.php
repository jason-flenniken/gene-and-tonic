@props(['tone' => 'neutral', 'label'])

@php
    $dots = ['ok' => 'bg-ok', 'warn' => 'bg-warn', 'bad' => 'bg-bad', 'neutral' => 'bg-zinc-400', 'active' => 'bg-juniper'];
    $texts = ['ok' => 'text-ok-text', 'warn' => 'text-warn-text', 'bad' => 'text-bad-text', 'neutral' => 'text-ink-muted', 'active' => 'text-juniper'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 text-[12px] font-bold tracking-[0.04em] uppercase', $texts[$tone] ?? $texts['neutral']]) }}>
    <span class="size-2 shrink-0 rounded-full {{ $dots[$tone] ?? $dots['neutral'] }}" aria-hidden="true"></span>{{ $label }}
</span>
