<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\EffectData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\HealthTopic;
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

    #[Url(except: '')]
    public string $effectTopic = '';

    public function mount(int|string $supplement, SupplementRepository $supplements): void
    {
        $model = is_numeric($supplement) ? $supplements->find((int) $supplement) : null;

        abort_if($model === null, 404);

        $this->supplementId = $model->id;
        $this->name = $model->name;
        $this->normalizeTab();
        $this->normalizeEffectTopic();
    }

    public function updatedEffectTopic(): void
    {
        $this->normalizeEffectTopic();
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

    /**
     * Effect categories in use, in enum order, with their counts. Empty for legacy research with no categories.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    #[Computed]
    public function effectTopics(): array
    {
        $counts = [];

        foreach ($this->research?->effects ?? [] as $effect) {
            if ($effect->category !== null) {
                $counts[$effect->category->value] = ($counts[$effect->category->value] ?? 0) + 1;
            }
        }

        $topics = [];

        foreach (HealthTopic::cases() as $topic) {
            if (isset($counts[$topic->value])) {
                $topics[] = ['value' => $topic->value, 'label' => $topic->label(), 'count' => $counts[$topic->value]];
            }
        }

        return $topics;
    }

    /** @return list<EffectData> */
    #[Computed]
    public function visibleEffects(): array
    {
        $effects = $this->research?->effects ?? [];

        if (HealthTopic::tryFrom($this->effectTopic) === null) {
            return $effects;
        }

        return array_values(array_filter($effects, fn (EffectData $effect) => $effect->category?->value === $this->effectTopic));
    }

    private function normalizeEffectTopic(): void
    {
        if (HealthTopic::tryFrom($this->effectTopic) === null) {
            $this->effectTopic = '';
        }
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
    }
};
?>

@php
    $research = $this->research;
    $active = $research?->status->isActive() ?? false;
    $topics = $this->effectTopics;
    $visibleEffects = $this->visibleEffects;
    $slotViews = fn (string $slot) => app(\Modules\Shared\Contracts\PageActions::class)->for($slot);
    $card = 'rounded-card border border-line p-5';
    $summary = $research?->summary;
@endphp

<div @if ($active) wire:poll.3s @endif>
<x-ui.panel narrow>
    <nav aria-label="Breadcrumb" class="flex flex-wrap gap-2 text-[14px] text-ink-subtle">
        <a href="{{ route('supplements.index') }}" class="font-medium text-juniper hover:text-juniper-hover">Supplements</a>
        <span aria-hidden="true">&rsaquo;</span>
        <span aria-current="page">{{ $research?->name ?? $name }}</span>
    </nav>

    <section class="flex flex-col gap-3.5 rounded-section bg-juniper-tint p-6" data-hero>
        <div class="flex items-start gap-4">
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <h1 class="text-[30px] leading-[1.15] font-bold tracking-[-0.02em] text-ink">{{ $research?->name ?? $name }}</h1>
                @if (filled($research?->kicker))
                    <span data-kicker class="text-[12px] font-bold tracking-[0.08em] text-ink-muted uppercase">{{ $research->kicker }}</span>
                @endif
            </div>
            <span class="flex size-14 shrink-0 items-center justify-center rounded-[14px] border border-juniper-line bg-surface text-juniper">
                <x-icon.capsule class="size-[30px]" />
            </span>
        </div>

        @if ($research && $research->topics !== [])
            <div class="flex flex-wrap gap-2" data-topics>
                @foreach ($research->topics as $topic)
                    <span class="rounded-lg border border-juniper-line bg-surface px-2.5 py-1 text-[13px] font-medium text-ink">{{ $topic->label() }}</span>
                @endforeach
            </div>
        @endif

        @if ($research && $research->status !== \Modules\Supplements\Enums\ResearchStatus::Complete)
            <div class="flex flex-col gap-2" data-run-status>
                <x-ui.status :tone="$research->status->tone()" :label="$research->status->label()" />
                @if ($active)
                    <x-ui.progress-steps :step="$research->status->step() ?? 1" :total="4" :label="$research->status->label()" class="max-w-[680px]" />
                @endif
            </div>
        @endif

        @if ($research && $research->status === \Modules\Supplements\Enums\ResearchStatus::Complete)
            @if (filled($summary))
                <div x-data="{ open: false }" class="max-w-[680px]" data-summary>
                    <p class="text-[15px] leading-[1.6] whitespace-pre-line text-ink-body" x-bind:class="open ? '' : 'line-clamp-3'">{{ $summary }}</p>
                    @if (mb_strlen($summary) > 240)
                        <button type="button" class="mt-1 text-[14px] font-semibold text-juniper hover:text-juniper-hover" x-on:click="open = ! open" x-text="open ? 'Show less' : 'Read more'">Read more</button>
                    @endif
                </div>
            @else
                <p class="text-[15px] text-ink-muted">No summary available.</p>
            @endif
        @endif

        <div class="mt-1 flex flex-wrap items-center gap-2.5">
            @foreach ($slotViews('supplement.header') as $actionView)
                @include($actionView, ['supplementId' => $supplementId, 'status' => $research?->status])
            @endforeach
            @if ($research)
                <x-ui.button variant="on-tint" wire:click="rerun" :disabled="$active">Re-run</x-ui.button>
            @endif
        </div>
    </section>

    <x-ui.notice>This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.</x-ui.notice>

    <x-ui.segmented>
        <x-ui.segmented.item :active="$tab === 'overview'" wire:click="$set('tab', 'overview')">Overview</x-ui.segmented.item>
        <x-ui.segmented.item :active="$tab === 'effects'" wire:click="$set('tab', 'effects')">Effects ({{ count($research?->effects ?? []) }})</x-ui.segmented.item>
        <x-ui.segmented.item :active="$tab === 'mechanisms'" wire:click="$set('tab', 'mechanisms')">Mechanisms ({{ count($research?->mechanisms ?? []) }})</x-ui.segmented.item>
        <x-ui.segmented.item :active="$tab === 'studies'" wire:click="$set('tab', 'studies')">Studies ({{ count($research?->studies ?? []) }})</x-ui.segmented.item>
        <x-ui.segmented.item :active="$tab === 'genetics'" wire:click="$set('tab', 'genetics')">Genetics ({{ count($research?->geneInteractions ?? []) }})</x-ui.segmented.item>
        <x-ui.segmented.item :active="$tab === 'sources'" wire:click="$set('tab', 'sources')">Sources ({{ count($research?->sources ?? []) }})</x-ui.segmented.item>
    </x-ui.segmented>

    @if ($tab === 'overview')
        <div role="tabpanel" data-panel="overview" class="flex flex-col gap-6">
            @if ($research === null)
                <div class="{{ $card }}">
                    <h2 class="text-[18px] font-bold text-ink">No research yet</h2>
                    <p class="mt-1 text-[15px] text-ink-muted">Research has not been started for this supplement.</p>
                </div>
            @elseif ($active)
                <div class="{{ $card }}">
                    <h2 class="text-[18px] font-bold text-ink">Research in progress</h2>
                    <p class="mt-1 text-[15px] text-ink-muted">Status: {{ $research->status->label() }}. This page updates on its own.</p>
                </div>
            @elseif ($research->status === \Modules\Supplements\Enums\ResearchStatus::Failed)
                <div class="{{ $card }}">
                    <h2 class="text-[18px] font-bold text-ink">Research failed</h2>
                    @if ($research->error)
                        <p class="mt-1 text-[15px] text-ink-muted">{{ $research->error }}</p>
                    @endif
                </div>
            @else
                @auth
                    <button type="button" wire:click="$set('tab', 'genetics')" class="flex min-h-[52px] w-full items-center gap-3 rounded-row bg-lime-tint px-4 text-left text-[15px] font-medium text-ink hover:brightness-95" data-genes-link>
                        <x-icon.helix class="size-5 shrink-0 text-juniper" />
                        <span class="flex-1">How does {{ $research->name }} fit my genes?</span>
                        <span aria-hidden="true" class="text-ink-subtle">&rsaquo;</span>
                    </button>
                @endauth

                <div class="grid gap-6 [grid-template-columns:repeat(auto-fit,minmax(min(340px,100%),1fr))]">
                    <section class="flex flex-col gap-3" data-section="effects">
                        <h2 class="text-[18px] font-bold text-ink">Health effects</h2>
                        <x-supplements::show.effect-chips :topics="$topics" :active="$effectTopic" :total="count($research->effects)" />
                        <div class="flex flex-col">
                            @forelse (array_slice($visibleEffects, 0, 4) as $effect)
                                <x-supplements::show.effect-row :effect="$effect" wire:key="overview-effect-{{ $loop->index }}" />
                            @empty
                                <p class="py-2 text-[15px] text-ink-muted">No effects recorded.</p>
                            @endforelse
                        </div>
                        @if ($visibleEffects !== [])
                            <x-ui.button variant="secondary" class="!h-[46px] !text-[14px]" wire:click="$set('tab', 'effects')">Show all effects</x-ui.button>
                        @endif
                    </section>

                    <div class="flex flex-col gap-4" data-section="your-genes">
                        @foreach ($slotViews('supplement.panels') as $panelView)
                            @include($panelView, ['supplementId' => $supplementId])
                        @endforeach
                    </div>
                </div>

                <x-ui.section title="Safety notes" class="border-t border-line-soft pt-5" data-section="safety">
                    <p class="text-[15px] leading-[1.6] whitespace-pre-line text-ink-body">{{ $research->safetyNotes ?? 'No safety notes recorded.' }}</p>
                    @if ($research->completedAt)
                        <p class="text-[13px] text-ink-muted">Completed {{ $research->completedAt->toDayDateTimeString() }}</p>
                    @endif
                </x-ui.section>
            @endif
        </div>
    @elseif ($tab === 'effects')
        <div role="tabpanel" data-panel="effects" class="flex flex-col gap-3">
            <x-supplements::show.effect-chips :topics="$topics" :active="$effectTopic" :total="count($research?->effects ?? [])" />
            @forelse ($visibleEffects as $effect)
                <x-supplements::show.effect-row :effect="$effect" :sources="$this->sourcesByKey" :detail="true" wire:key="effect-{{ $loop->index }}" />
            @empty
                <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">No effects recorded.</p></div>
            @endforelse
        </div>
    @elseif ($tab === 'mechanisms')
        <div role="tabpanel" data-panel="mechanisms" class="flex flex-col gap-3">
            @forelse ($research?->mechanisms ?? [] as $mechanism)
                <div wire:key="mechanism-{{ $loop->index }}" class="rounded-row border border-line px-4 py-3.5">
                    <p class="text-[15px] leading-normal text-ink">{{ $mechanism->description }}</p>
                    <x-supplements::source-badges :keys="$mechanism->sourceKeys" :sources="$this->sourcesByKey" />
                </div>
            @empty
                <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">No mechanisms recorded.</p></div>
            @endforelse
        </div>
    @elseif ($tab === 'studies')
        <div role="tabpanel" data-panel="studies">
            @if (($research?->studies ?? []) === [])
                <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">No studies recorded.</p></div>
            @else
                <div class="overflow-x-auto rounded-card border border-line">
                    <table class="w-full text-left text-[14px]">
                        <thead class="border-b border-line bg-surface-muted text-ink-muted">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold">Title</th>
                                <th class="px-3 py-2.5 font-semibold">Design</th>
                                <th class="px-3 py-2.5 font-semibold">Population</th>
                                <th class="px-3 py-2.5 font-semibold">n</th>
                                <th class="px-3 py-2.5 font-semibold">Year</th>
                                <th class="px-3 py-2.5 font-semibold">Finding</th>
                                <th class="px-3 py-2.5 font-semibold">Grade</th>
                                <th class="px-3 py-2.5 font-semibold">PubMed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ($research->studies as $study)
                                <tr wire:key="study-{{ $loop->index }}" class="align-top">
                                    <td class="px-3 py-2.5 font-semibold text-ink">{{ $study->title }}</td>
                                    <td class="px-3 py-2.5">{{ $study->design }}</td>
                                    <td class="px-3 py-2.5">{{ $study->population }}</td>
                                    <td class="px-3 py-2.5">{{ $study->sampleSize }}</td>
                                    <td class="px-3 py-2.5">{{ $study->year }}</td>
                                    <td class="px-3 py-2.5">{{ $study->finding }}</td>
                                    <td class="px-3 py-2.5">
                                        @if ($study->evidenceGrade)
                                            <x-ui.grade :grade="$study->evidenceGrade->value" />
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        @if ($study->pubmedUrl())
                                            <a href="{{ $study->pubmedUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-juniper hover:text-juniper-hover">PMID {{ $study->pmid }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif ($tab === 'genetics')
        <div role="tabpanel" data-panel="genetics" class="flex flex-col gap-4">
            @if ($research === null || ! $research->geneticsChecked)
                <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">Re-run research to check genetic interactions.</p></div>
            @else
                <div class="flex flex-col gap-3">
                    @forelse ($research->geneInteractions as $interaction)
                        <div wire:key="gene-{{ $loop->index }}" class="rounded-row border border-line px-4 py-3.5">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <h3 class="text-[15px] font-bold text-ink">{{ $interaction->gene }}</h3>
                                @if ($interaction->rsid)
                                    <span class="text-[13px] text-ink-subtle">{{ $interaction->rsid }}</span>
                                @endif
                                @if ($interaction->allele)
                                    <span class="rounded-lg border border-line-input px-2 py-0.5 text-[12px] font-semibold text-ink-muted">Allele {{ $interaction->allele }}</span>
                                @endif
                                <x-ui.status class="ml-auto" :tone="$interaction->relation->tone()" :label="$interaction->relation->label()" />
                            </div>
                            <p class="mt-1.5 text-[14px] leading-normal text-ink-body">{{ $interaction->explanation }}</p>
                            <x-supplements::source-badges :keys="$interaction->sourceKeys" :sources="$this->sourcesByKey" />
                        </div>
                    @empty
                        <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">No genetic interactions found in the sources.</p></div>
                    @endforelse
                </div>
            @endif

            @foreach ($slotViews('supplement.panels') as $panelView)
                @include($panelView, ['supplementId' => $supplementId])
            @endforeach
        </div>
    @else
        <div role="tabpanel" data-panel="sources">
            @if (($research?->sources ?? []) === [])
                <div class="{{ $card }}"><p class="text-[15px] text-ink-muted">No sources recorded.</p></div>
            @else
                <div class="overflow-x-auto rounded-card border border-line">
                    <table class="w-full text-left text-[14px]">
                        <thead class="border-b border-line bg-surface-muted text-ink-muted">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold">Key</th>
                                <th class="px-3 py-2.5 font-semibold">Title</th>
                                <th class="px-3 py-2.5 font-semibold">Domain</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft">
                            @foreach ($research->sources as $source)
                                <tr wire:key="source-{{ $loop->index }}">
                                    <td class="px-3 py-2.5">{{ $source->key }}</td>
                                    <td class="px-3 py-2.5">
                                        @if ($source->safeUrl())
                                            <a href="{{ $source->safeUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-juniper hover:text-juniper-hover">{{ $source->title }}</a>
                                        @else
                                            {{ $source->title }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5">{{ $source->domain }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-ui.panel>
</div>
