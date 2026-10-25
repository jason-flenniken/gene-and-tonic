@props(['narrow' => false])

<div {{ $attributes->class([
    'mx-auto flex w-full flex-col gap-6 rounded-panel border border-line bg-surface p-[clamp(18px,3vw,36px)]',
    'max-w-[1080px]' => ! $narrow,
    'max-w-[900px]' => $narrow,
]) }}>
    {{ $slot }}
</div>
