<?php

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Modules\Supplements\Actions\AddSupplement;
use Modules\Supplements\Actions\DeleteSupplement;
use Modules\Supplements\Actions\StartResearch;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Repositories\SupplementRepository;
use Modules\Supplements\Support\ActionThrottle;

new #[Title('Supplements')] class extends Component
{
    use WithPagination;

    private const array STATUSES = ['all', 'complete', 'active', 'failed'];

    public string $name = '';

    public ?int $deleteId = null;

    #[Url]
    public string $status = 'all';

    #[Url(as: 'selected')]
    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->normalizeStatus();
    }

    public function updatedStatus(): void
    {
        $this->normalizeStatus();
        $this->resetPage();
    }

    public function selectSupplement(int $id): void
    {
        $this->selectedId = $id;
    }

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
        $this->normalizeStatus();

        return app(SupplementRepository::class)->paginateWithResearch(10, $this->status);
    }

    /**
     * Chip counts: all, complete, active (queued, searching or analyzing) and failed.
     *
     * @return array{all: int, complete: int, active: int, failed: int}
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = app(SupplementRepository::class)->countByStatus();
        $active = array_sum(array_map(fn (ResearchStatus $s): int => $byStatus[$s->value] ?? 0, [ResearchStatus::Queued, ResearchStatus::Searching, ResearchStatus::Analyzing]));

        return [
            'all' => array_sum($byStatus),
            'complete' => $byStatus[ResearchStatus::Complete->value] ?? 0,
            'active' => $active,
            'failed' => $byStatus[ResearchStatus::Failed->value] ?? 0,
        ];
    }

    /** The supplement shown in the preview: the selected one if it exists, else the first complete one. */
    #[Computed]
    public function selected(): ?Supplement
    {
        $supplements = app(SupplementRepository::class);
        $selected = $this->selectedId !== null ? $supplements->find($this->selectedId) : null;

        return $selected ?? $supplements->firstComplete();
    }

    #[Computed]
    public function selectedResearch(): ?SupplementResearchData
    {
        $selected = $this->selected;

        return $selected === null ? null : app(SupplementResearchReader::class)->forSupplement($selected->id);
    }

    /**
     * The top three effects, strongest evidence first.
     *
     * @return list<\Modules\Supplements\Data\EffectData>
     */
    #[Computed]
    public function topEffects(): array
    {
        $effects = $this->selectedResearch?->effects ?? [];

        usort($effects, fn ($a, $b): int => $a->evidenceGrade->rank() <=> $b->evidenceGrade->rank());

        return array_slice($effects, 0, 3);
    }

    private function normalizeStatus(): void
    {
        if (! in_array($this->status, self::STATUSES, true)) {
            $this->status = 'all';
        }
    }
};
?>

<div @if ($this->hasActiveRuns) wire:poll.3s @endif>
<x-ui.panel>
    <x-ui.page-header title="Supplements" subtitle="Name a supplement. We read the published research and check it against your genes." />

    <form wire:submit="add" class="flex flex-wrap gap-2.5">
        <label for="new-supplement" class="sr-only">Supplement name</label>
        <input
            id="new-supplement"
            type="text"
            wire:model="name"
            maxlength="100"
            placeholder="e.g. Vitamin D3, Creatine, Omega-3"
            class="box-border h-[52px] min-w-0 flex-[1_1_300px] rounded-full border border-line-input bg-surface-input px-5 text-base text-ink placeholder:text-ink-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-juniper"
        >
        <x-ui.button type="submit" loading="add" class="!h-[52px] !px-7 !text-base">Research it</x-ui.button>
        @error('name')
            <p class="basis-full px-5 text-[13px] text-bad-text" role="alert">{{ $message }}</p>
        @enderror
    </form>

    @php($counts = $this->counts)
    @php($supplements = $this->supplements)

    <div class="grid overflow-hidden rounded-section border border-line md:grid-cols-2">
        <div class="flex flex-col gap-1 bg-surface-muted px-4 py-5 md:border-r md:border-line">
            <div class="flex flex-wrap gap-1.5 px-1 pb-3" role="group" aria-label="Filter by status">
                <x-ui.chip :active="$status === 'all'" :count="$counts['all']" wire:click="$set('status', 'all')">All</x-ui.chip>
                <x-ui.chip :active="$status === 'complete'" :count="$counts['complete']" wire:click="$set('status', 'complete')">Complete</x-ui.chip>
                <x-ui.chip :active="$status === 'active'" :count="$counts['active']" wire:click="$set('status', 'active')">In progress</x-ui.chip>
                @if ($counts['failed'] > 0)
                    <x-ui.chip :active="$status === 'failed'" :count="$counts['failed']" wire:click="$set('status', 'failed')">Failed</x-ui.chip>
                @endif
            </div>

            @forelse ($supplements as $supplement)
                <x-supplements::catalog.row :supplement="$supplement" :selected="$this->selected?->id === $supplement->id" wire:key="supplement-{{ $supplement->id }}" />
            @empty
                <p class="px-4 py-3 text-[15px] text-ink-muted">
                    @if ($counts['all'] === 0)
                        No supplements yet. Add one above to start researching.
                    @else
                        No supplements match this filter.
                    @endif
                </p>
            @endforelse

            <div class="mt-2 px-2">
                {{ $supplements->links() }}
            </div>
        </div>

        <div class="hidden md:block">
            @if ($this->selected)
                <x-supplements::catalog.preview :supplement="$this->selected" :research="$this->selectedResearch" :effects="$this->topEffects" />
            @else
                <p class="p-7 text-[15px] text-ink-muted">Nothing to preview yet. Research a supplement to see its top effects here.</p>
            @endif
        </div>
    </div>

    <flux:modal name="delete-supplement" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete supplement?</flux:heading>
                <flux:text class="mt-2">This removes the supplement and all of its research. This cannot be undone.</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" class="!h-11">Cancel</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="delete" class="!h-11">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</x-ui.panel>
</div>
