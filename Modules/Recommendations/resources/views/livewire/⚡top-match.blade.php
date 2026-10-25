<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Shared\Contracts\PageActions;

new class extends Component
{
    /**
     * Null for guests. Computed, not public, so the user's genotypes never land in the component snapshot.
     */
    #[Computed]
    public function top(): ?SupplementMatchesData
    {
        $userId = auth()->id();

        if ($userId === null) {
            return null;
        }

        return app(GeneticRelevanceReader::class)->topForUser((int) $userId, 1)[0] ?? null;
    }

    #[Computed]
    public function hasProfile(): bool
    {
        $userId = auth()->id();

        return $userId !== null && ! app(GeneticProfileReader::class)->forUser((int) $userId)->isEmpty();
    }

    /** @return list<string> */
    #[Computed]
    public function headerViews(): array
    {
        return app(PageActions::class)->for('supplement.header');
    }
};
?>

<div class="contents">
    @guest
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-section bg-juniper-tint p-6" data-hero="sign-in">
            <p class="text-[15px] font-semibold text-ink">Sign in to see how supplements relate to your genes</p>
            <x-ui.button :href="route('login')">Sign in</x-ui.button>
        </section>
    @else
        @if (! $this->hasProfile)
            <section class="flex flex-wrap items-center justify-between gap-4 rounded-section bg-juniper-tint p-6" data-hero="add-results">
                <p class="text-[15px] font-semibold text-ink">Add your genetic results to see how supplements relate to them.</p>
                <x-ui.button :href="route('genetics.profile')">Add your genetic results</x-ui.button>
            </section>
        @elseif ($this->top !== null)
            @php
                $top = $this->top;
                $count = count($top->matches);
                $genes = array_values(array_unique(array_map(fn ($match) => $match->gene, $top->matches)));
                $geneList = count($genes) > 1 ? implode(', ', array_slice($genes, 0, -1)).' and '.end($genes) : ($genes[0] ?? '');
            @endphp
            <section class="flex flex-wrap items-center gap-x-8 gap-y-5 rounded-section bg-juniper-tint p-6" data-hero="top-match">
                <div class="flex min-w-[min(380px,100%)] flex-1 flex-col gap-2.5">
                    <span class="text-xs font-bold tracking-[0.08em] text-juniper">GENE MATCH</span>
                    <h2 class="text-[22px] leading-[1.3] font-bold tracking-[-0.01em] text-ink">{{ $count }} of your variants {{ $count === 1 ? 'relates' : 'relate' }} to {{ $top->name }}</h2>
                    <p class="text-[15px] leading-[1.55] text-ink-body">Your {{ $geneList }} results line up with this supplement's research. See what it says before you decide anything.</p>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2.5">
                        <x-ui.button :href="route('supplements.show', $top->supplementId)">Open {{ $top->name }}</x-ui.button>
                        @foreach ($this->headerViews as $actionView)
                            @include($actionView, ['supplementId' => $top->supplementId, 'status' => $top->status])
                        @endforeach
                    </div>
                </div>
                <div class="flex min-w-[min(260px,100%)] flex-[0_1_300px] flex-col gap-2">
                    @foreach (array_slice($top->matches, 0, 3) as $match)
                        @php($alleles = array_slice(preg_split('//u', $match->genotype, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2))
                        <div wire:key="top-match-{{ $loop->index }}" data-variant-gene="{{ $match->gene }}" class="flex items-center gap-3 rounded-xl bg-surface px-3.5 py-2.5">
                            <span class="flex-1 text-[15px] font-bold text-ink">{{ $match->gene }}</span>
                            <x-ui.allele-pair :alleles="$alleles" :effect="$match->allele" :size="26" />
                            <span class="sr-only" aria-hidden="true"><span data-genotype>{{ $match->genotype }}</span></span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endguest
</div>
