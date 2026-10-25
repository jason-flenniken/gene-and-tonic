<div {{ $attributes->class('flex items-center gap-3 rounded-row bg-lime-tint px-4 py-3 text-[14px] text-ink-body') }}>
    <x-icon.info class="size-[18px] shrink-0 text-juniper" />
    <div class="min-w-0">{{ $slot }}</div>
</div>
