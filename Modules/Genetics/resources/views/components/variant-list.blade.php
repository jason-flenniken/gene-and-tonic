@props(['sections', 'openKeys' => [], 'links' => [], 'lookingUp' => 0])

@php
    use Modules\Genetics\Enums\LookupStatus;
    use Modules\Genetics\Enums\VariantGroup;
    use Modules\Genetics\Support\VariantGrouping;
@endphp

<div class="flex flex-col gap-3">
    @foreach ($sections as $section)
        @php
            $isUnlooked = $section['key'] === 'unlooked';
            $group = $isUnlooked ? null : VariantGroup::from($section['key']);
            $count = count($section['variants']);
            $open = in_array($section['key'], $openKeys, true);
        @endphp
        <details wire:key="section-{{ $section['key'] }}" data-section="{{ $section['key'] }}" @if ($open) open @endif class="group overflow-hidden rounded-section border border-line">
            <summary
                wire:click.prevent="toggleSection(@js($section['key']))"
                @class([
                    'flex min-h-16 cursor-pointer list-none items-center gap-3.5 px-[18px] py-2 text-ink [&::-webkit-details-marker]:hidden',
                    'bg-lime-tint' => $isUnlooked,
                    'bg-surface group-open:bg-surface-muted' => ! $isUnlooked,
                ])
            >
                @if ($isUnlooked)
                    <x-ui.icon-tile tone="stone"><x-icon.search /></x-ui.icon-tile>
                    <span class="flex-1 text-[16px] font-bold">
                        {{ $section['label'] }}
                        <span class="font-normal text-ink-subtle">· {{ $count }} {{ $count === 1 ? 'variant' : 'variants' }}, {{ $lookingUp }} queued</span>
                    </span>
                    <x-ui.button wire:click.stop="lookUpAll" loading="lookUpAll">Look up now</x-ui.button>
                @else
                    <x-ui.icon-tile :tone="$group->tone()"><x-dynamic-component :component="'icon.'.$group->icon()" /></x-ui.icon-tile>
                    <span class="flex-1 text-[16px] font-semibold group-open:font-bold">
                        {{ $section['label'] }}
                        <span class="font-normal text-ink-subtle">· {{ $count }} {{ $count === 1 ? 'variant' : 'variants' }}</span>
                    </span>
                @endif
                <x-icon.chevron-down class="size-5 shrink-0 text-ink-subtle transition-transform group-open:rotate-180" />
            </summary>

            @foreach ($section['variants'] as $variant)
                @php
                    $copies = VariantGrouping::copies($variant);
                    $linked = $links[$variant->rsid] ?? [];
                @endphp
                <div wire:key="variant-{{ $variant->rsid }}" class="flex flex-wrap items-start gap-x-5 gap-y-2.5 border-t border-line-soft p-[18px]">
                    <div class="flex basis-[150px] flex-col gap-0.5">
                        <span data-variant-gene="{{ $variant->gene }}" class="text-[16px] font-bold">{{ $variant->gene }}</span>
                        @if ($variant->variantName)
                            <span class="text-[13px] text-ink-muted">{{ $variant->variantName }}</span>
                        @endif
                        <span class="text-[13px] text-ink-subtle">{{ $variant->rsid }}</span>
                    </div>

                    @if (! $isUnlooked)
                        <div class="basis-20">
                            <x-ui.allele-pair :alleles="VariantGrouping::alleles($variant->genotype)" :effect="$variant->riskAllele" />
                        </div>
                        <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-2">
                            @if ($variant->affects)
                                <p class="text-[14px] leading-[1.55] text-ink-body">{{ $variant->affects }}</p>
                            @endif
                            @if ($variant->genotypeEffect !== null && $variant->genotypeEffect !== '')
                                <p class="rounded-tile bg-juniper-tint px-3 py-2.5 text-[14px] leading-normal text-ink-body"><strong class="font-bold">Your {{ $variant->genotype }}{{ $copies > 0 ? ', '.($copies === 1 ? 'one copy' : 'two copies') : '' }}:</strong> {{ $variant->genotypeEffect }}</p>
                            @endif
                            @if ($linked !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($linked as $supplement)
                                        <a href="{{ route('supplements.show', $supplement->supplementId) }}" class="rounded-[8px] border border-juniper-line px-2.5 py-0.5 text-[13px] font-semibold text-juniper hover:bg-juniper-tint">Linked: {{ $supplement->name }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="flex min-w-0 flex-[1_1_320px] items-center justify-between gap-3">
                            <span class="text-[14px] text-ink-muted">
                                @if ($variant->status === LookupStatus::LookingUp)
                                    Looking up…
                                @elseif ($variant->status === LookupStatus::Failed)
                                    Lookup failed
                                @else
                                    Not looked up yet
                                @endif
                            </span>
                            @if ($variant->status !== LookupStatus::LookingUp)
                                <x-ui.button variant="secondary" wire:click="lookUp('{{ $variant->rsid }}')" loading="lookUp" class="!h-11 !px-4 !text-[14px]">Look up</x-ui.button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </details>
    @endforeach
</div>
