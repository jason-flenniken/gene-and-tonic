<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Repositories\SupplementRepository;

new class extends Component
{
    #[Computed]
    public function total(): int
    {
        return app(SupplementRepository::class)->total();
    }

    #[Computed]
    public function inProgress(): int
    {
        $counts = app(SupplementRepository::class)->countByStatus();

        return collect(ResearchStatus::cases())
            ->filter(fn (ResearchStatus $status): bool => $status->isActive())
            ->sum(fn (ResearchStatus $status): int => $counts[$status->value] ?? 0);
    }
};
?>

<x-ui.stat
    label="Supplements"
    :value="number_format($this->total)"
    :caption="$this->inProgress > 0 ? $this->inProgress.' in progress' : 'Research complete'"
    data-stat="supplements"
/>
