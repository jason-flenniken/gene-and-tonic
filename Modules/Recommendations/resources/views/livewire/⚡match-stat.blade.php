<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\SupplementMatchesData;

new class extends Component
{
    private const ALL = 1000;

    /**
     * Empty for guests. Computed, not public, so genotypes never land in the snapshot.
     *
     * @return list<SupplementMatchesData>
     */
    #[Computed]
    public function supplements(): array
    {
        $userId = auth()->id();

        return $userId === null ? [] : app(GeneticRelevanceReader::class)->topForUser((int) $userId, self::ALL);
    }
};
?>

<div class="contents">
    @auth
        @php($supplements = count($this->supplements))
        <x-ui.stat
            label="Gene matches"
            :value="number_format(array_sum(array_map(fn ($supplement) => count($supplement->matches), $this->supplements)))"
            :caption="'Across '.$supplements.' '.str('supplement')->plural($supplements)"
            data-stat="gene-matches"
        />
    @endauth
</div>
