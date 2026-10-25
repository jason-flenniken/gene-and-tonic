@props(['grade'])

@php
    $grades = [
        'strong' => ['dot' => 'bg-ok', 'text' => 'text-ok-text'],
        'moderate' => ['dot' => 'bg-juniper', 'text' => 'text-juniper'],
        'limited' => ['dot' => 'bg-warn', 'text' => 'text-warn-text'],
    ];
    $style = $grades[$grade] ?? $grades['limited'];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-2 rounded-lg border border-line-input px-2.5 py-1 text-[11px] font-bold tracking-[0.04em] uppercase', $style['text']]) }}>
    <span class="size-2 shrink-0 rounded-full {{ $style['dot'] }}" aria-hidden="true"></span>{{ $grade }}
</span>
