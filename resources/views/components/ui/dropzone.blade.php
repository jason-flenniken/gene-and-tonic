@props(['label', 'help' => null, 'filename' => null])

<label class="flex cursor-pointer flex-col items-center gap-2 rounded-card border-[1.5px] border-dashed border-[#C9D9CF] bg-[#FAFCFA] p-[22px] text-center focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-juniper">
    <input type="file" class="sr-only" {{ $attributes }}>
    <x-icon.upload class="size-7 text-juniper" />
    <span class="text-[15px] font-semibold text-ink">{{ $label }}</span>
    @if ($help)
        <span class="text-[13px] leading-normal text-ink-muted">{{ $help }}</span>
    @endif
    @if ($filename)
        <span data-filename class="text-[13px] font-semibold text-ink">{{ $filename }}</span>
    @endif
    <span class="mt-1.5 inline-flex h-[42px] items-center rounded-full bg-juniper px-5 text-[14px] font-semibold text-white">Choose file</span>
</label>
