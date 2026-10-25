<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Actions\StartResearch;
use Modules\Supplements\Repositories\SupplementRepository;
use Modules\Supplements\Support\ActionThrottle;

new #[Title('Supplement')] class extends Component
{
    private const TABS = ['overview', 'effects', 'mechanisms', 'studies', 'genetics', 'sources'];

    public int $supplementId;

    public string $name = '';

    #[Url(as: 'tab', except: 'overview')]
    public string $tab = 'overview';

    public function mount(int|string $supplement, SupplementRepository $supplements): void
    {
        $model = is_numeric($supplement) ? $supplements->find((int) $supplement) : null;

        abort_if($model === null, 404);

        $this->supplementId = $model->id;
        $this->name = $model->name;
        $this->normalizeTab();
    }

    public function updatedTab(): void
    {
        $this->normalizeTab();
    }

    public function rerun(SupplementRepository $supplements, StartResearch $startResearch): void
    {
        $supplement = $supplements->find($this->supplementId);

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
    public function research(): ?SupplementResearchData
    {
        return app(SupplementResearchReader::class)->forSupplement($this->supplementId);
    }

    /** @return array<string, \Modules\Supplements\Data\SourceData> */
    #[Computed]
    public function sourcesByKey(): array
    {
        $byKey = [];

        foreach ($this->research?->sources ?? [] as $source) {
            $byKey[$source->key] = $source;
        }

        return $byKey;
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
    }
};
?>

<div class="space-y-6">
    @php($research = $this->research)

    <div>
        <a href="{{ route('home', ['tab' => 'supplements']) }}" class="text-sm text-zinc-500 hover:underline">&larr; Back to supplements</a>
        <div class="mt-2 flex items-center gap-3">
            <flux:heading size="xl">{{ $research?->name ?? $name }}</flux:heading>
            @if ($research)
                <flux:badge color="{{ $research->status->color() }}" size="sm">{{ $research->status->label() }}</flux:badge>
            @endif
            <div class="ml-auto flex items-center gap-3">
                @if ($research && ! $research->status->isActive())
                    <flux:button size="sm" wire:click="rerun">Re-run</flux:button>
                @endif
                @foreach (app(\Modules\Shared\Contracts\PageActions::class)->for('supplement.header') as $actionView)
                    @include($actionView, ['supplementId' => $supplementId, 'status' => $research?->status])
                @endforeach
            </div>
        </div>
    </div>

    <x-tabs>
        <x-tabs.item :active="$tab === 'overview'" wire:click="$set('tab', 'overview')">Overview</x-tabs.item>
        <x-tabs.item :active="$tab === 'effects'" wire:click="$set('tab', 'effects')">Effects ({{ count($research?->effects ?? []) }})</x-tabs.item>
        <x-tabs.item :active="$tab === 'mechanisms'" wire:click="$set('tab', 'mechanisms')">Mechanisms ({{ count($research?->mechanisms ?? []) }})</x-tabs.item>
        <x-tabs.item :active="$tab === 'studies'" wire:click="$set('tab', 'studies')">Studies ({{ count($research?->studies ?? []) }})</x-tabs.item>
        <x-tabs.item :active="$tab === 'genetics'" wire:click="$set('tab', 'genetics')">Genetics ({{ count($research?->geneInteractions ?? []) }})</x-tabs.item>
        <x-tabs.item :active="$tab === 'sources'" wire:click="$set('tab', 'sources')">Sources ({{ count($research?->sources ?? []) }})</x-tabs.item>
    </x-tabs>

    @if ($tab === 'overview')
        <div role="tabpanel" data-panel="overview" class="space-y-4">
            <flux:callout icon="information-circle" color="zinc">
                <flux:callout.text>This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.</flux:callout.text>
            </flux:callout>

            @if ($research === null)
                <flux:card>
                    <flux:heading>No research yet</flux:heading>
                    <flux:text class="mt-1">Research has not been started for this supplement.</flux:text>
                </flux:card>
            @elseif ($research->status->isActive())
                <flux:card>
                    <flux:heading>Research in progress</flux:heading>
                    <flux:text class="mt-1">Status: {{ $research->status->label() }}. Check back shortly.</flux:text>
                </flux:card>
            @elseif ($research->status === \Modules\Supplements\Enums\ResearchStatus::Failed)
                <flux:card>
                    <flux:heading>Research failed</flux:heading>
                    @if ($research->error)
                        <flux:text class="mt-1">{{ $research->error }}</flux:text>
                    @endif
                </flux:card>
            @else
                <flux:card class="space-y-4">
                    <div>
                        <flux:heading>Summary</flux:heading>
                        <flux:text class="mt-1 whitespace-pre-line">{{ $research->summary ?? 'No summary available.' }}</flux:text>
                    </div>
                    <div>
                        <flux:heading>Safety notes</flux:heading>
                        <flux:text class="mt-1 whitespace-pre-line">{{ $research->safetyNotes ?? 'No safety notes recorded.' }}</flux:text>
                    </div>
                    @if ($research->completedAt)
                        <flux:text size="sm">Completed {{ $research->completedAt->toDayDateTimeString() }}</flux:text>
                    @endif
                </flux:card>
            @endif
        </div>
    @elseif ($tab === 'effects')
        <div role="tabpanel" data-panel="effects" class="space-y-3">
            @forelse ($research?->effects ?? [] as $effect)
                <flux:card wire:key="effect-{{ $loop->index }}">
                    <div class="flex items-start justify-between gap-3">
                        <flux:heading>{{ $effect->claim }}</flux:heading>
                        <flux:badge size="sm">{{ ucfirst($effect->evidenceGrade->value) }}</flux:badge>
                    </div>
                    @if ($effect->outcome)
                        <flux:text class="mt-1">{{ $effect->outcome }}</flux:text>
                    @endif
                    <x-supplements::source-badges :keys="$effect->sourceKeys" :sources="$this->sourcesByKey" />
                </flux:card>
            @empty
                <flux:card><flux:text>No effects recorded.</flux:text></flux:card>
            @endforelse
        </div>
    @elseif ($tab === 'mechanisms')
        <div role="tabpanel" data-panel="mechanisms" class="space-y-3">
            @forelse ($research?->mechanisms ?? [] as $mechanism)
                <flux:card wire:key="mechanism-{{ $loop->index }}">
                    <flux:text>{{ $mechanism->description }}</flux:text>
                    <x-supplements::source-badges :keys="$mechanism->sourceKeys" :sources="$this->sourcesByKey" />
                </flux:card>
            @empty
                <flux:card><flux:text>No mechanisms recorded.</flux:text></flux:card>
            @endforelse
        </div>
    @elseif ($tab === 'studies')
        <div role="tabpanel" data-panel="studies">
            @if (($research?->studies ?? []) === [])
                <flux:card><flux:text>No studies recorded.</flux:text></flux:card>
            @else
                <flux:card class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 text-zinc-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Title</th>
                                <th class="py-2 pr-4 font-medium">Design</th>
                                <th class="py-2 pr-4 font-medium">Population</th>
                                <th class="py-2 pr-4 font-medium">n</th>
                                <th class="py-2 pr-4 font-medium">Year</th>
                                <th class="py-2 pr-4 font-medium">Finding</th>
                                <th class="py-2 pr-4 font-medium">Grade</th>
                                <th class="py-2 font-medium">PubMed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($research->studies as $study)
                                <tr wire:key="study-{{ $loop->index }}" class="align-top">
                                    <td class="py-2 pr-4 font-medium">{{ $study->title }}</td>
                                    <td class="py-2 pr-4">{{ $study->design }}</td>
                                    <td class="py-2 pr-4">{{ $study->population }}</td>
                                    <td class="py-2 pr-4">{{ $study->sampleSize }}</td>
                                    <td class="py-2 pr-4">{{ $study->year }}</td>
                                    <td class="py-2 pr-4">{{ $study->finding }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($study->evidenceGrade)
                                            <flux:badge size="sm">{{ ucfirst($study->evidenceGrade->value) }}</flux:badge>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        @if ($study->pubmedUrl())
                                            <a href="{{ $study->pubmedUrl() }}" target="_blank" rel="noopener noreferrer" class="underline">PMID {{ $study->pmid }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </flux:card>
            @endif
        </div>
    @elseif ($tab === 'genetics')
        <div role="tabpanel" data-panel="genetics" class="space-y-3">
            @if ($research === null || ! $research->geneticsChecked)
                <flux:card><flux:text>Re-run research to check genetic interactions.</flux:text></flux:card>
            @else
                @forelse ($research->geneInteractions as $interaction)
                    <flux:card wire:key="gene-{{ $loop->index }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading>{{ $interaction->gene }}</flux:heading>
                            @if ($interaction->rsid)
                                <flux:badge size="sm">{{ $interaction->rsid }}</flux:badge>
                            @endif
                            @if ($interaction->allele)
                                <flux:badge size="sm">Allele {{ $interaction->allele }}</flux:badge>
                            @endif
                            <flux:badge size="sm" color="{{ $interaction->relation->color() }}">{{ $interaction->relation->label() }}</flux:badge>
                        </div>
                        <flux:text class="mt-1">{{ $interaction->explanation }}</flux:text>
                        <x-supplements::source-badges :keys="$interaction->sourceKeys" :sources="$this->sourcesByKey" />
                    </flux:card>
                @empty
                    <flux:card><flux:text>No genetic interactions found in the sources.</flux:text></flux:card>
                @endforelse
            @endif
        </div>
    @else
        <div role="tabpanel" data-panel="sources">
            @if (($research?->sources ?? []) === [])
                <flux:card><flux:text>No sources recorded.</flux:text></flux:card>
            @else
                <flux:card class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-zinc-200 text-zinc-500">
                            <tr>
                                <th class="py-2 pr-4 font-medium">Key</th>
                                <th class="py-2 pr-4 font-medium">Title</th>
                                <th class="py-2 font-medium">Domain</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($research->sources as $source)
                                <tr wire:key="source-{{ $loop->index }}">
                                    <td class="py-2 pr-4">{{ $source->key }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($source->safeUrl())
                                            <a href="{{ $source->safeUrl() }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $source->title }}</a>
                                        @else
                                            {{ $source->title }}
                                        @endif
                                    </td>
                                    <td class="py-2">{{ $source->domain }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </flux:card>
            @endif
        </div>
    @endif

    @foreach (app(\Modules\Shared\Contracts\PageActions::class)->for('supplement.panels') as $panelView)
        @include($panelView, ['supplementId' => $supplementId])
    @endforeach
</div>
