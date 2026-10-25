@props(['title', 'subtitle' => null])

<header {{ $attributes->class('flex flex-wrap items-start justify-between gap-4') }}>
    <div class="flex min-w-0 flex-col gap-1.5">
        <h1 class="text-[30px] leading-[1.15] font-bold tracking-[-0.02em] text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-[15px] text-ink-muted">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-3">{{ $actions }}</div>
    @endisset
</header>
