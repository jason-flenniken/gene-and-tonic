@props(['variant' => 'primary', 'href' => null])

@php
    $classes = [
        'inline-flex h-[46px] items-center justify-center gap-2 rounded-full px-6 text-[15px] font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-juniper',
        'bg-juniper text-white hover:bg-juniper-hover' => $variant === 'primary',
        'bg-surface-sunken text-ink hover:bg-line' => $variant === 'secondary',
        'bg-white text-ink hover:bg-surface-muted' => $variant === 'on-tint',
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
