@props(['active' => false])

<button
    type="button"
    role="tab"
    aria-selected="{{ $active ? 'true' : 'false' }}"
    {{ $attributes->class([
        'inline-flex min-h-11 shrink-0 items-center rounded-full px-4 py-2 text-sm whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-juniper',
        'bg-surface font-bold text-ink shadow-segment' => $active,
        'font-semibold text-ink-nav hover:text-ink' => ! $active,
    ]) }}
>
    {{ $slot }}
</button>
