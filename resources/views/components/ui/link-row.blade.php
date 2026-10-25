@props(['href', 'meta' => null, 'title', 'variant' => 'bordered'])

<a href="{{ $href }}" {{ $attributes->class([
    'flex w-full items-center gap-3 text-ink hover:bg-surface-muted',
    'rounded-row border border-line px-4 py-3.5' => $variant === 'bordered',
    'min-h-16 border-b border-line-soft py-2' => $variant === 'divided',
]) }}>
    @isset($leading)
        {{ $leading }}
    @endisset
    <span class="flex min-w-0 flex-1 flex-col gap-0.5">
        @if ($meta)
            <span class="text-[13px] text-ink-subtle">{{ $meta }}</span>
        @endif
        <span class="text-[15px] font-medium">{{ $title }}</span>
    </span>
    <x-icon.chevron-right class="size-4 shrink-0 text-ink-subtle" />
</a>
