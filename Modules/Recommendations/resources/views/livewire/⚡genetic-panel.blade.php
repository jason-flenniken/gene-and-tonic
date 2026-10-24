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

<flux:card class="space-y-4" data-panel="your-genetics">
    <flux:heading size="lg">Your genetics</flux:heading>

    @auth
        @php($relevance = $this->relevance)

        <flux:callout icon="information-circle" color="zinc">
            <flux:callout.text>Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</flux:callout.text>
        </flux:callout>

        @if (! $relevance->hasProfile)
            <flux:text>Add your genetic results to see how this supplement relates to them.</flux:text>
            <flux:link href="{{ route('genetics.profile') }}">Open My genetics</flux:link>
        @elseif (! $relevance->geneticsChecked)
            <flux:text>Re-run research to check genetic interactions.</flux:text>
        @elseif (! $relevance->hasMatches())
            <flux:text>No known interactions with your tested variants.</flux:text>
        @else
            <div class="space-y-3">
                @foreach ($relevance->matches as $match)
                    <div wire:key="match-{{ $loop->index }}" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading>{{ $match->gene }}</flux:heading>
                            <flux:badge size="sm">{{ $match->rsid }}</flux:badge>
                            <flux:badge size="sm">Genotype <span data-genotype>{{ $match->genotype }}</span></flux:badge>
                            @if ($match->copies !== null)
                                <flux:badge size="sm">{{ $match->copies }} {{ $match->copies === 1 ? 'copy' : 'copies' }}</flux:badge>
                            @endif
                            <flux:badge size="sm" color="{{ $match->relation->color() }}">{{ $match->relation->label() }}</flux:badge>
                        </div>
                        <flux:text class="mt-1">{{ $match->explanation }}</flux:text>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <flux:text>Sign in to see how this relates to your genetics.</flux:text>
        <flux:link href="{{ route('login') }}">Sign in</flux:link>
    @endauth
</flux:card>
