<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Genetics\Contracts\GeneticProfileReader;

new class extends Component
{
    #[Computed]
    public function count(): int
    {
        return app(GeneticProfileReader::class)->forUser((int) auth()->id())->count();
    }
};
?>

<flux:card class="space-y-4">
    <flux:heading size="lg">My genetics</flux:heading>

    @auth
        <div data-stat="variants">
            <div class="text-sm text-zinc-500">SNPs on file</div>
            <div class="text-2xl font-semibold">{{ number_format($this->count) }}</div>
        </div>
        <flux:link href="{{ route('genetics.profile') }}">Open My genetics</flux:link>
    @else
        <flux:text>Sign in to add your genetic results.</flux:text>
        <flux:link href="{{ route('login') }}">Sign in</flux:link>
    @endauth
</flux:card>
