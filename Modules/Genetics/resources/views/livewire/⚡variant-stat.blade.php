<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\VariantData;

new class extends Component
{
    /**
     * One profile read per render. Zeros for guests: nothing is read without a signed-in user.
     *
     * @return array{total: int, lookedUp: int}
     */
    #[Computed]
    public function counts(): array
    {
        $userId = auth()->id();

        if ($userId === null) {
            return ['total' => 0, 'lookedUp' => 0];
        }

        $variants = app(GeneticProfileReader::class)->forUser((int) $userId)->variants;

        return [
            'total' => count($variants),
            'lookedUp' => count(array_filter($variants, fn (VariantData $variant): bool => $variant->status->isLookedUp())),
        ];
    }
};
?>

<div class="contents">
    @auth
        <x-ui.stat label="Variants" :value="number_format($this->counts['total'])" :caption="number_format($this->counts['lookedUp']).' looked up'" data-stat="variants" />
    @endauth
</div>
