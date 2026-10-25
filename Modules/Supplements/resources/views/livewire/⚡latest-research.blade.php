<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\HealthTopic;
use Modules\Supplements\Enums\ResearchStatus;

new class extends Component
{
    /**
     * The four most recently completed supplements, newest first.
     *
     * @return list<SupplementSummaryData>
     */
    #[Computed]
    public function rows(): array
    {
        $complete = array_values(array_filter(
            app(SupplementResearchReader::class)->all(),
            fn (SupplementSummaryData $summary): bool => $summary->status === ResearchStatus::Complete,
        ));

        usort($complete, fn (SupplementSummaryData $a, SupplementSummaryData $b): int => ($b->completedAt?->getTimestamp() ?? 0) <=> ($a->completedAt?->getTimestamp() ?? 0));

        return array_slice($complete, 0, 4);
    }

    public function title(SupplementSummaryData $summary): string
    {
        if ($summary->effectsCount === 0) {
            return $summary->name.': no effects were graded';
        }

        return $summary->name.': '.$summary->effectsCount.' '.str('effect')->plural($summary->effectsCount)
            .' across '.$summary->studiesCount.' '.str('study')->plural($summary->studiesCount);
    }

    public function meta(SupplementSummaryData $summary): string
    {
        if ($summary->topics !== []) {
            return implode(' · ', array_map(fn (HealthTopic $topic): string => $topic->label(), $summary->topics));
        }

        return $summary->completedAt === null ? 'Complete' : 'Complete · '.$summary->completedAt->diffForHumans();
    }
};
?>

<x-ui.section title="Latest research" :link-href="route('supplements.index')" link-label="All supplements" data-section="latest-research">
    @forelse ($this->rows as $summary)
        <x-ui.link-row wire:key="latest-{{ $summary->supplementId }}" :href="route('supplements.show', $summary->supplementId)" :meta="$this->meta($summary)" :title="$this->title($summary)" />
    @empty
        <p class="text-[15px] text-ink-muted">No research has finished yet.</p>
    @endforelse
</x-ui.section>
