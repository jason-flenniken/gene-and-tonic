@props(['label', 'value', 'caption' => null])

<div {{ $attributes->class('flex flex-col gap-1 rounded-card border border-line bg-surface px-[18px] py-4') }}>
    <span class="text-[13px] text-ink-muted">{{ $label }}</span>
    <span class="tabular text-[28px] leading-tight font-bold text-ink">{{ $value }}</span>
    @if ($caption)
        <span class="text-[13px] text-ink-muted">{{ $caption }}</span>
    @endif
</div>
