<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Intelligence\Enums\UsageWindow;
use Modules\Intelligence\Repositories\AiRequestRepository;
use Modules\Intelligence\Repositories\UsageSummary;

new class extends Component
{
    #[Computed]
    public function summary(): UsageSummary
    {
        return app(AiRequestRepository::class)->summary(UsageWindow::Day);
    }
};
?>

<x-ui.stat
    label="AI spend"
    :value="'$'.number_format($this->summary->estimatedCostUsd, 2)"
    :caption="number_format($this->summary->calls).' '.str('call')->plural($this->summary->calls).' in 24h'"
    data-stat="ai-spend"
/>
