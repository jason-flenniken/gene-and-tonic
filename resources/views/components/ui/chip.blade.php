@props(['active' => false, 'count' => null])

<button
    type="button"
    aria-pressed="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->class([
        'inline-flex h-9 items-center gap-1.5 rounded-tile border px-3.5 text-[13px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-juniper',
        'border-juniper bg-juniper text-white' => $active,
        'border-line-input bg-surface text-ink hover:bg-surface-muted' => ! $active,
    ]) }}
>
    <span>{{ $slot }}</span>
    @if ($count !== null)
        <span @class(['tabular font-medium', 'text-white/80' => $active, 'text-ink-muted' => ! $active])>{{ $count }}</span>
    @endif
</button>
