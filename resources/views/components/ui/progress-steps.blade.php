@props(['step', 'total' => 4, 'label'])

@php($percent = $total > 0 ? max(0, min(100, round($step / $total * 100))) : 0)

<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    <span class="text-[13px] font-semibold text-juniper">{{ $label }} · step {{ $step }} of {{ $total }}</span>
    <span class="flex h-1 rounded-sm bg-juniper-tint" role="presentation">
        <span class="rounded-sm bg-juniper" style="width: {{ $percent }}%"></span>
    </span>
</div>
