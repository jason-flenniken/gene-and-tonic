<?php

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('UI components')] class extends Component
{
    use WithFileUploads;

    public $file = null;
};
?>

<x-ui.panel>
    <x-ui.page-header title="UI components" subtitle="Every x-ui component in every state. Local only.">
        <x-slot:actions>
            <x-ui.button variant="secondary">Action</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.section title="x-brand and x-icon">
        <div class="flex flex-wrap items-center gap-6">
            <x-brand.mark :size="20" />
            <x-brand.mark :size="34" />
            <x-brand.mark :size="64" />
            <x-brand.lockup :height="38" />
        </div>
        <div class="flex flex-wrap gap-4 text-juniper">
            @foreach (['arrow-up', 'capsule', 'chart', 'chevron-down', 'chevron-right', 'chevron-up', 'file', 'group-dots', 'group-drop', 'group-flame', 'group-heart', 'group-leaf', 'group-moon', 'group-shield', 'group-smile', 'helix', 'home', 'info', 'search', 'upload'] as $icon)
                <span class="flex w-20 flex-col items-center gap-1 text-[11px] text-ink-muted">
                    <x-dynamic-component :component="'icon.'.$icon" class="size-6 text-juniper" />
                    {{ $icon }}
                </span>
            @endforeach
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.panel" link-href="#" link-label="Link label">
        <x-ui.panel narrow class="!p-4">Narrow panel (900 px)</x-ui.panel>
    </x-ui.section>

    <x-ui.section title="x-ui.stat">
        <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-3">
            <x-ui.stat label="Variants" value="128" caption="across 9 groups" data-stat="variants" />
            <x-ui.stat label="Supplements" value="12" />
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.icon-tile">
        <div class="flex flex-wrap items-center gap-3">
            @foreach (['green', 'butter', 'peach', 'coral', 'blue', 'rose', 'teal', 'indigo', 'leaf', 'stone'] as $tone)
                <x-ui.icon-tile :tone="$tone"><x-icon.helix /></x-ui.icon-tile>
            @endforeach
            <x-ui.icon-tile tone="green" :size="32">MT</x-ui.icon-tile>
            <x-ui.icon-tile tone="blue" :size="44">FUT2</x-ui.icon-tile>
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.link-row">
        <x-ui.link-row href="#" meta="Complete · 2 hours ago" title="Methylfolate: 4 effects across 6 studies" />
        <div>
            <x-ui.link-row href="#" variant="divided" meta="12 variants" title="Methylation">
                <x-slot:leading><x-ui.icon-tile tone="green"><x-icon.helix /></x-ui.icon-tile></x-slot:leading>
            </x-ui.link-row>
            <x-ui.link-row href="#" variant="divided" title="Without meta" />
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.button">
        <div class="flex flex-wrap items-center gap-3">
            <x-ui.button>Primary</x-ui.button>
            <x-ui.button variant="secondary">Secondary</x-ui.button>
            <div class="rounded-row bg-juniper-tint p-3"><x-ui.button variant="on-tint">On tint</x-ui.button></div>
            <x-ui.button href="#">As link</x-ui.button>
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.segmented and x-tabs">
        <x-ui.segmented>
            <x-ui.segmented.item :active="true">Active</x-ui.segmented.item>
            <x-ui.segmented.item>Inactive</x-ui.segmented.item>
        </x-ui.segmented>
        <x-tabs>
            <x-tabs.item :active="true">Tabs active</x-tabs.item>
            <x-tabs.item>Tabs inactive</x-tabs.item>
        </x-tabs>
    </x-ui.section>

    <x-ui.section title="x-ui.chip">
        <div class="flex flex-wrap gap-2">
            <x-ui.chip :active="true" :count="12">Active</x-ui.chip>
            <x-ui.chip :count="3">Inactive</x-ui.chip>
            <x-ui.chip>No count</x-ui.chip>
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.status">
        <div class="flex flex-wrap gap-4">
            @foreach (['ok', 'warn', 'bad', 'neutral', 'active'] as $tone)
                <x-ui.status :tone="$tone" :label="$tone" />
            @endforeach
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.progress-steps">
        <x-ui.progress-steps :step="1" :total="4" label="Queued" />
        <x-ui.progress-steps :step="2" :total="4" label="Searching sources" />
        <x-ui.progress-steps :step="3" :total="4" label="Analyzing" />
    </x-ui.section>

    <x-ui.section title="x-ui.grade">
        <div class="flex flex-wrap gap-3">
            <x-ui.grade grade="strong" />
            <x-ui.grade grade="moderate" />
            <x-ui.grade grade="limited" />
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.allele-pair and x-ui.copies">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex items-center gap-3"><x-ui.allele-pair :alleles="['G', 'T']" effect="G" /><x-ui.copies :count="1" /></div>
            <div class="flex items-center gap-3"><x-ui.allele-pair :alleles="['G', 'G']" effect="G" /><x-ui.copies :count="2" /></div>
            <div class="flex items-center gap-3"><x-ui.allele-pair :alleles="['T', 'T']" effect="G" :size="26" /><x-ui.copies :count="0" /></div>
            <div class="flex items-center gap-3"><x-ui.allele-pair :alleles="['A', 'G']" :effect="null" :size="28" /></div>
            <div class="flex items-center gap-3"><x-ui.allele-pair :alleles="['D']" effect="D" /></div>
        </div>
    </x-ui.section>

    <x-ui.section title="x-ui.notice">
        <x-ui.notice>Educational summary of published research, not medical advice or a diagnosis.</x-ui.notice>
    </x-ui.section>

    <x-ui.section title="x-ui.dropzone">
        <x-ui.dropzone wire:model="file" label="Drop a raw data file or a PDF report" help="23andMe, AncestryDNA or a PDF · up to 25 MB" />
        <x-ui.dropzone wire:model="file" label="With a file chosen" filename="Genetic Markers.csv" />
    </x-ui.section>
</x-ui.panel>
