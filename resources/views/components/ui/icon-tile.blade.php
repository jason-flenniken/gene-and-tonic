@props(['tone' => 'stone', 'size' => 40])

@php
    $tones = [
        'green' => 'bg-tone-green-bg text-tone-green-fg',
        'butter' => 'bg-tone-butter-bg text-tone-butter-fg',
        'peach' => 'bg-tone-peach-bg text-tone-peach-fg',
        'coral' => 'bg-tone-coral-bg text-tone-coral-fg',
        'blue' => 'bg-tone-blue-bg text-tone-blue-fg',
        'rose' => 'bg-tone-rose-bg text-tone-rose-fg',
        'teal' => 'bg-tone-teal-bg text-tone-teal-fg',
        'indigo' => 'bg-tone-indigo-bg text-tone-indigo-fg',
        'leaf' => 'bg-tone-leaf-bg text-tone-leaf-fg',
        'stone' => 'bg-tone-stone-bg text-tone-stone-fg',
    ];
    $sizes = [
        32 => 'size-8 text-[12px]',
        40 => 'size-10 text-[13px]',
        44 => 'size-11 text-[13px]',
    ];
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center justify-center rounded-tile font-bold [&_svg]:size-5',
    $tones[$tone] ?? $tones['stone'],
    $sizes[(int) $size] ?? $sizes[40],
]) }}>{{ $slot }}</span>
