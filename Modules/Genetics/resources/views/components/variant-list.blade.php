@props(['sections', 'openKeys' => []])

<div class="space-y-3">
    @foreach ($sections as $section)
        <details wire:key="section-{{ $section['key'] }}" @if (in_array($section['key'], $openKeys, true)) open @endif class="rounded-lg border border-zinc-200 dark:border-zinc-700">
            <summary wire:click.prevent="toggleSection(@js($section['key']))" class="flex cursor-pointer items-center justify-between px-4 py-2 font-medium">
                <span>{{ $section['label'] }}</span>
                <span class="text-sm font-normal text-zinc-500">{{ count($section['variants']) }}</span>
            </summary>
            <div class="overflow-x-auto px-4 pb-3">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Gene</th>
                            <th class="py-2 pr-4 font-medium">rsID</th>
                            <th class="py-2 pr-4 font-medium">Genotype</th>
                            <th class="py-2 pr-4 font-medium">Category</th>
                            <th class="py-2 pr-4 font-medium">Affects</th>
                            <th class="py-2 font-medium"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($section['variants'] as $variant)
                            <tr wire:key="variant-{{ $variant->rsid }}">
                                <td class="py-2 pr-4 font-medium">{{ $variant->gene }}</td>
                                <td class="py-2 pr-4">{{ $variant->rsid }}</td>
                                <td class="py-2 pr-4">{{ $variant->genotype }}</td>
                                @if ($section['key'] !== 'unlooked')
                                    <td class="py-2 pr-4">{{ $variant->category }}</td>
                                    <td class="py-2 pr-4">
                                        {{ $variant->affects }}
                                        @if ($variant->genotypeEffect !== null && $variant->genotypeEffect !== '')
                                            <span class="mt-1 block rounded bg-amber-50 px-2 py-1 text-amber-900 dark:bg-amber-950 dark:text-amber-100">Your {{ $variant->genotype }}: {{ $variant->genotypeEffect }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2"></td>
                                @else
                                    <td class="py-2 pr-4 text-zinc-500" colspan="2">
                                        @if ($variant->status === \Modules\Genetics\Enums\LookupStatus::LookingUp)
                                            Looking up…
                                        @elseif ($variant->status === \Modules\Genetics\Enums\LookupStatus::Failed)
                                            Lookup failed
                                        @else
                                            Not looked up yet
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if ($variant->status !== \Modules\Genetics\Enums\LookupStatus::LookingUp)
                                            <flux:button size="sm" wire:click="lookUp('{{ $variant->rsid }}')">Look up</flux:button>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endforeach
</div>
