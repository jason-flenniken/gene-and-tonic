@props(['title', 'linkHref' => null, 'linkLabel' => null])

<section {{ $attributes->class('flex flex-col gap-3') }}>
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="text-[18px] font-bold text-ink">{{ $title }}</h2>
        @if ($linkHref && $linkLabel)
            <a href="{{ $linkHref }}" class="text-[14px] font-semibold text-juniper hover:text-juniper-hover">{{ $linkLabel }}</a>
        @endif
    </div>
    {{ $slot }}
</section>
