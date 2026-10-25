<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\VariantData;

new class extends Component
{
    /**
     * Zero for guests: nothing is read without a signed-in user.
     */
    #[Computed]
    public function total(): int
    {
        $userId = auth()->id();

        return $userId === null ? 0 : app(GeneticProfileReader::class)->forUser((int) $userId)->count();
    }

    #[Computed]
    public function lookedUp(): int
    {
        $userId = auth()->id();

        if ($userId === null) {
            return 0;
        }

        $variants = app(GeneticProfileReader::class)->forUser((int) $userId)->variants;

        return count(array_filter($variants, fn (VariantData $variant): bool => $variant->status->isLookedUp()));
    }
};
?>

<div class="contents">
    @auth
        <x-ui.stat label="Variants" :value="number_format($this->total)" :caption="number_format($this->lookedUp).' looked up'" data-stat="variants" />
    @endauth
</div>
