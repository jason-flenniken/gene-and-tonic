@props(['count'])

<span {{ $attributes->class('text-[11px] font-bold tracking-[0.06em] text-ink-muted') }}>{{ $count }} {{ (int) $count === 1 ? 'COPY' : 'COPIES' }}</span>
