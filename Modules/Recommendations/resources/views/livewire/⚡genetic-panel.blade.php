<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticRelevanceData;

new class extends Component
{
    #[Locked]
    public int $supplementId;

    /**
     * Null for guests. Computed, not public, so the user's genotypes never land in the component snapshot.
     */
    #[Computed]
    public function relevance(): ?GeneticRelevanceData
    {
        $userId = auth()->id();

        if ($userId === null) {
            return null;
        }

        return app(GeneticRelevanceReader::class)->forUserAndSupplement((int) $userId, $this->supplementId);
    }
};
?>

<section class="flex flex-col gap-3.5 rounded-section border border-line p-5" data-panel="your-genetics">
    @auth
        @php($relevance = $this->relevance)

        @if ($relevance->hasMatches())
            @php($count = count($relevance->matches))
            <div class="flex items-center gap-3.5">
                <svg width="56" height="56" viewBox="0 0 56 56" role="img" aria-label="{{ $count }} matching variants" class="shrink-0">
                    <circle cx="28" cy="28" r="23" fill="none" stroke="var(--color-juniper-tint)" stroke-width="6" />
                    <circle cx="28" cy="28" r="23" fill="none" stroke="var(--color-juniper)" stroke-width="6" stroke-linecap="round" stroke-dasharray="144.5 144.5" transform="rotate(-90 28 28)" />
                    <text x="28" y="33" text-anchor="middle" font-size="15" font-weight="700" fill="var(--color-ink)" style="font-family: inherit">{{ $count }}</text>
                </svg>
                <div class="flex flex-col gap-0.5">
                    <h2 class="text-[18px] font-bold text-ink">Your genes</h2>
                    <span class="text-[14px] text-ink-muted">{{ $count }} of your variants {{ $count === 1 ? 'relates' : 'relate' }} to this supplement</span>
                </div>
            </div>
        @else
            <h2 class="text-[18px] font-bold text-ink">Your genes</h2>
        @endif

        @if (! $relevance->hasProfile)
            <p class="text-[15px] text-ink-muted">Add your genetic results to see how this supplement relates to them.</p>
            <a href="{{ route('genetics.profile') }}" class="text-[15px] font-semibold text-juniper hover:text-juniper-hover">Open My genetics</a>
        @elseif (! $relevance->geneticsChecked)
            <p class="text-[15px] text-ink-muted">Re-run research to check genetic interactions.</p>
        @elseif (! $relevance->hasMatches())
            <p class="text-[15px] text-ink-muted">No known interactions with your tested variants.</p>
        @else
            <div class="flex flex-col">
                @foreach ($relevance->matches as $match)
                    @php($alleles = array_slice(preg_split('//u', $match->genotype, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2))
                    <div wire:key="match-{{ $loop->index }}" data-variant-gene="{{ $match->gene }}" class="flex flex-col gap-1.5 border-t border-line-soft py-3 last:border-b">
                        <div class="flex items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="text-[15px] font-bold text-ink">{{ $match->gene }}</div>
                                <div class="text-[13px] text-ink-subtle">{{ $match->rsid }}@if (filled($match->variantName)) &middot; {{ $match->variantName }}@endif</div>
                            </div>
                            <x-ui.allele-pair :alleles="$alleles" :effect="$match->allele" :size="28" />
                            <span class="sr-only" aria-hidden="true"><span data-genotype>{{ $match->genotype }}</span></span>
                            @if ($match->copies !== null)
                                <x-ui.copies :count="$match->copies" class="w-16 text-right" />
                            @endif
                        </div>
                        <div class="flex items-start gap-3">
                            <x-ui.status :tone="$match->relation->tone()" :label="$match->relation->label()" class="shrink-0" />
                            <p class="text-[14px] leading-normal text-ink-body">{{ $match->explanation }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <p class="text-[13px] leading-normal text-ink-muted">Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</p>
    @else
        <h2 class="text-[18px] font-bold text-ink">Your genes</h2>
        <p class="text-[15px] text-ink-muted">Sign in to see how this relates to your genetics.</p>
        <a href="{{ route('login') }}" class="text-[15px] font-semibold text-juniper hover:text-juniper-hover">Sign in</a>
    @endauth
</section>
