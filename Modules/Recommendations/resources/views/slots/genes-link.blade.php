@php
    $userId = auth()->id();
    $showGenesLink = $userId !== null
        && app(\Modules\Recommendations\Contracts\GeneticRelevanceReader::class)->forUserAndSupplement((int) $userId, (int) $supplementId)->hasProfile;
@endphp

@if ($showGenesLink)
    <button type="button" wire:click="$set('tab', 'genetics')" class="flex min-h-[52px] w-full items-center gap-3 rounded-row bg-lime-tint px-4 text-left text-[15px] font-medium text-ink hover:brightness-95" data-genes-link>
        <x-icon.helix class="size-5 shrink-0 text-juniper" />
        <span class="flex-1">How does {{ $name }} fit my genes?</span>
        <span aria-hidden="true" class="text-ink-subtle">&rsaquo;</span>
    </button>
@endif
