<?php

use Flux\Flux;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Genetics\Actions\AddVariant;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;

new #[Title('My genetics')] class extends Component
{
    public string $rsid = '';

    public string $genotype = '';

    public string $gene = '';

    public function addVariant(AddVariant $addVariant): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        $this->validate([
            'rsid' => ['required', 'string', 'regex:/^rs\d{1,10}$/i'],
            'genotype' => [
                'required', 'string', 'max:5',
                function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        if (Genotype::normalize((string) $value) === null) {
                            $fail('Enter the genotype as one or two alleles, for example A/G.');
                        }
                    } catch (\InvalidArgumentException) {
                        $fail('Use one or two alleles from A, C, G, T, D or I.');
                    }
                },
            ],
            'gene' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{1,15}$/'],
        ], [
            'rsid.regex' => 'Use an rsID such as rs1801133.',
            'gene.regex' => 'Use a gene symbol such as MTHFR.',
        ]);

        $limiterKey = 'genetics-add-variant:'.$userId;

        if (RateLimiter::tooManyAttempts($limiterKey, 20)) {
            throw ValidationException::withMessages(['rsid' => 'Too many requests, try again in a minute.']);
        }

        RateLimiter::hit($limiterKey, 60);

        try {
            $addVariant->handle($userId, $this->rsid, $this->genotype, $this->gene);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['rsid' => 'That SNP could not be saved. Check the values and try again.']);
        }

        $this->reset('rsid', 'genotype', 'gene');
        unset($this->variants);
        Flux::toast(text: 'SNP saved.', variant: 'success');
    }

    /** @return list<VariantData> */
    #[Computed]
    public function variants(): array
    {
        $variants = app(GeneticProfileReader::class)->forUser((int) auth()->id())->variants;

        usort($variants, fn (VariantData $a, VariantData $b): int => [$a->gene ?? "\u{10FFFF}", $a->rsid] <=> [$b->gene ?? "\u{10FFFF}", $b->rsid]);

        return $variants;
    }

    public function isLookedUp(VariantData $variant): bool
    {
        return ! in_array($variant->status, [LookupStatus::Pending, LookupStatus::Failed], true);
    }
};
?>

<div class="space-y-6">
    <flux:callout icon="information-circle">
        <flux:callout.text>Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</flux:callout.text>
    </flux:callout>

    <flux:card>
        <form wire:submit="addVariant" class="grid items-start gap-3 md:grid-cols-4">
            <flux:input wire:model="rsid" label="rsID" placeholder="rs1801133" maxlength="12" />
            <flux:input wire:model="genotype" label="Genotype" placeholder="A/G" maxlength="5" />
            <flux:input wire:model="gene" label="Gene (optional)" placeholder="MTHFR" maxlength="15" />
            <flux:button type="submit" variant="primary" class="mt-6">Add SNP</flux:button>
        </form>
    </flux:card>

    <flux:card>
        @if ($this->variants === [])
            <flux:text>No SNPs yet. Add one above.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Gene</th>
                            <th class="py-2 pr-4 font-medium">rsID</th>
                            <th class="py-2 pr-4 font-medium">Genotype</th>
                            <th class="py-2 pr-4 font-medium">Category</th>
                            <th class="py-2 pr-4 font-medium">Affects</th>
                            <th class="py-2 font-medium">Your genotype</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->variants as $variant)
                            <tr wire:key="variant-{{ $variant->rsid }}">
                                <td class="py-2 pr-4 font-medium">{{ $variant->gene }}</td>
                                <td class="py-2 pr-4">{{ $variant->rsid }}</td>
                                <td class="py-2 pr-4">{{ $variant->genotype }}</td>
                                @if ($this->isLookedUp($variant))
                                    <td class="py-2 pr-4">{{ $variant->category }}</td>
                                    <td class="py-2 pr-4">{{ $variant->affects }}</td>
                                    <td class="py-2">{{ $variant->genotypeEffect }}</td>
                                @else
                                    <td class="py-2 text-zinc-500" colspan="3">Not looked up yet</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </flux:card>
</div>
