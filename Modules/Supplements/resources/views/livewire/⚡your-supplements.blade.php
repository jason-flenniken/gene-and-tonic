<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Shared\Contracts\PageActions;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\ResearchStatus;

new class extends Component
{
    private const TONES = ['green', 'blue', 'butter', 'peach', 'coral', 'rose', 'teal', 'indigo', 'leaf'];

    /** @return list<SupplementSummaryData> */
    #[Computed]
    public function supplements(): array
    {
        return array_values(array_filter(
            app(SupplementResearchReader::class)->all(),
            fn (SupplementSummaryData $summary): bool => $summary->status === ResearchStatus::Complete,
        ));
    }

    /** @return list<string> */
    #[Computed]
    public function cardViews(): array
    {
        return app(PageActions::class)->for('supplement.card');
    }

    /** The configured tile label for a known name, otherwise initials (several words) or the first letters. */
    public function abbreviation(string $name): string
    {
        $known = config('supplements.abbreviations', [])[mb_strtolower(trim($name))] ?? null;

        if (is_string($known) && $known !== '') {
            return $known;
        }

        $words = preg_split('/[^\p{L}\p{N}]+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) > 1) {
            return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 4))));
        }

        return mb_convert_case(mb_substr($words[0] ?? $name, 0, 4), MB_CASE_TITLE);
    }

    public function tone(string $name): string
    {
        return self::TONES[crc32(mb_strtolower($name)) % count(self::TONES)];
    }
};
?>

<x-ui.section title="Your supplements" :link-href="route('supplements.index')" link-label="Add a supplement" data-section="your-supplements">
    @if ($this->supplements === [])
        <x-ui.notice data-empty="supplements">
            No supplements yet. <a href="{{ route('supplements.index') }}" class="font-semibold text-juniper hover:text-juniper-hover">Add a supplement</a> to see its research here.
        </x-ui.notice>
    @else
        <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr))]">
            @foreach ($this->supplements as $summary)
                <a wire:key="supplement-{{ $summary->supplementId }}" href="{{ route('supplements.show', $summary->supplementId) }}" data-supplement-card class="flex flex-col gap-3 rounded-card border border-line p-4 text-ink hover:bg-surface-muted">
                    <span class="flex items-center gap-3">
                        <x-ui.icon-tile :tone="$this->tone($summary->name)" :size="44" data-abbreviation>{{ $this->abbreviation($summary->name) }}</x-ui.icon-tile>
                        <span class="flex-1 text-base font-bold">{{ $summary->name }}</span>
                    </span>
                    <span class="text-[13px] leading-normal text-ink-muted">
                        {{ $summary->effectsCount }} {{ str('effect')->plural($summary->effectsCount) }}
                        @foreach ($this->cardViews as $cardView)
                            @include($cardView, ['supplementId' => $summary->supplementId])
                        @endforeach
                        <br>
                        Researched {{ $summary->completedAt?->diffForHumans() ?? 'recently' }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-ui.section>
