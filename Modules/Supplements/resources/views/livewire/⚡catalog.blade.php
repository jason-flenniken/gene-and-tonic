<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Supplements\Actions\AddSupplement;
use Modules\Supplements\Actions\DeleteSupplement;
use Modules\Supplements\Actions\StartResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\SupplementRepository;
use Modules\Supplements\Support\ActionThrottle;

new #[Title('Supplements')] class extends Component
{
    use WithPagination;

    public string $name = '';

    public ?int $deleteId = null;

    public function add(AddSupplement $addSupplement): void
    {
        $this->validateOnly('name', ['name' => ['required', 'string']]);

        if (! ActionThrottle::attempt('supplements-add')) {
            $this->addError('name', ActionThrottle::MESSAGE);

            return;
        }

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

    public function retry(int $id, SupplementRepository $supplements, StartResearch $startResearch): void
    {
        $supplement = $supplements->find($id);

        if ($supplement === null) {
            return;
        }

        if (! ActionThrottle::attempt('supplements-run')) {
            Flux::toast(text: ActionThrottle::MESSAGE, variant: 'danger');

            return;
        }

        if ($startResearch->handle($supplement)) {
            Flux::toast(text: 'Research started.', variant: 'success');
        } else {
            Flux::toast(text: 'Research is already running.', variant: 'warning');
        }
    }

    #[Computed]
    public function hasActiveRuns(): bool
    {
        return $this->supplements->contains(fn (Supplement $s): bool => $s->research?->status->isActive() === true);
    }

    /** @return LengthAwarePaginator<int, Supplement> */
    #[Computed]
    public function supplements(): LengthAwarePaginator
    {
        return app(SupplementRepository::class)->paginateWithResearch(10);
    }
};
?>

<div class="space-y-6" @if ($this->hasActiveRuns) wire:poll.3s @endif>
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
                    <thead class="border-b border-zinc-200 text-zinc-500">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Name</th>
                            <th class="py-2 pr-4 font-medium">Status</th>
                            <th class="py-2 pr-4 font-medium">Effects</th>
                            <th class="py-2 pr-4 font-medium">Studies</th>
                            <th class="py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($this->supplements as $supplement)
                            <tr wire:key="supplement-{{ $supplement->id }}">
                                <td class="py-2 pr-4">
                                    <a href="{{ route('supplements.show', $supplement) }}" class="font-medium hover:underline">{{ $supplement->name }}</a>
                                </td>
                                <td class="py-2 pr-4">
                                    @if ($supplement->research)
                                        <flux:badge color="{{ $supplement->research->status->color() }}" size="sm">{{ $supplement->research->status->label() }}</flux:badge>
                                        @if ($supplement->research->error)
                                            <flux:text size="sm" class="mt-1 text-red-600">{{ $supplement->research->error }}</flux:text>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-2 pr-4">{{ $supplement->effects_count }}</td>
                                <td class="py-2 pr-4">{{ $supplement->studies_count }}</td>
                                <td class="space-x-1 py-2 text-right">
                                    @if ($supplement->research)
                                        @php($active = $supplement->research->status->isActive())
                                        @if ($supplement->research->status === \Modules\Supplements\Enums\ResearchStatus::Failed)
                                            <flux:button size="sm" wire:click="retry({{ $supplement->id }})">Retry</flux:button>
                                        @else
                                            <flux:button size="xs" variant="ghost" :disabled="$active" wire:click="retry({{ $supplement->id }})">Re-run</flux:button>
                                        @endif
                                    @endif
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
