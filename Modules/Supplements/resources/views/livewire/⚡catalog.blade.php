<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Supplements\Actions\AddSupplement;
use Modules\Supplements\Actions\DeleteSupplement;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\SupplementRepository;

new class extends Component
{
    use WithPagination;

    public string $name = '';

    public ?int $deleteId = null;

    public function add(AddSupplement $addSupplement): void
    {
        $this->validateOnly('name', ['name' => ['required', 'string']]);

        $supplement = $addSupplement->handle($this->name);

        $this->reset('name');
        $this->resetPage();
        Flux::toast(text: $supplement->name.' queued for research.', variant: 'success');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
    }

    public function delete(SupplementRepository $supplements, DeleteSupplement $deleteSupplement): void
    {
        $supplement = $this->deleteId !== null ? $supplements->find($this->deleteId) : null;

        if ($supplement !== null) {
            $deleteSupplement->handle($supplement);
            Flux::toast(text: 'Supplement deleted.', variant: 'success');
        }

        $this->deleteId = null;
        $this->modal('delete-supplement')->close();
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, Supplement> */
    #[Computed]
    public function supplements(): LengthAwarePaginator
    {
        return app(SupplementRepository::class)->paginateWithResearch(10);
    }

    public function detailUrl(Supplement $supplement): string
    {
        return Route::has('supplements.show')
            ? route('supplements.show', $supplement)
            : url('/supplements/'.$supplement->getKey());
    }
};
?>

<div class="space-y-6">
    <flux:card>
        <form wire:submit="add" class="flex items-start gap-3">
            <div class="flex-1">
                <flux:input wire:model="name" label="Supplement" placeholder="e.g. Magnesium glycinate" maxlength="100" />
            </div>
            <flux:button type="submit" variant="primary" class="mt-6">Add supplement</flux:button>
        </form>
    </flux:card>

    <flux:card>
        @if ($this->supplements->isEmpty())
            <flux:text>No supplements yet. Add one above to start researching.</flux:text>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-zinc-200 text-zinc-500 dark:border-zinc-700">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Name</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Effects</th>
                            <th class="py-2 pr-4 font-medium">Studies</th>
                            <th class="py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->supplements as $supplement)
                            <tr wire:key="supplement-{{ $supplement->id }}">
                                <td class="py-2 pr-4">
                                    <a href="{{ $this->detailUrl($supplement) }}" class="font-medium hover:underline">{{ $supplement->name }}</a>
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($supplement->research)
                                        <flux:badge color="{{ $supplement->research->status->color() }}" size="sm">{{ $supplement->research->status->label() }}</flux:badge>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $supplement->effects_count }}</td>
                                <td class="py-2 pr-4">{{ $supplement->studies_count }}</td>
                                <td class="py-2 text-right">
                                    <flux:modal.trigger name="delete-supplement">
                                        <flux:button size="sm" variant="ghost" wire:click="confirmDelete({{ $supplement->id }})">Delete</flux:button>
                                    </flux:modal.trigger>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $this->supplements->links() }}
            </div>
        @endif
    </flux:card>

    <flux:modal name="delete-supplement" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete supplement?</flux:heading>
                <flux:text class="mt-2">This removes the supplement and all of its research. This cannot be undone.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
