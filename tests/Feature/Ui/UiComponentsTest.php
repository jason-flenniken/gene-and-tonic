<?php

use Illuminate\Support\Facades\Blade;

function renderUi(string $template): string
{
    return Blade::render($template);
}

it('renders every icon with currentColor and no fixed size or metadata', function (string $icon) {
    $html = renderUi('<x-icon.'.$icon.' class="size-5" />');

    expect($html)
        ->toContain('stroke="currentColor"')
        ->toContain('class="size-5"')
        ->not->toContain('c2pa')
        ->not->toContain('width="24"');
})->with([
    'arrow-up', 'capsule', 'chart', 'chevron-down', 'chevron-right', 'chevron-up', 'file',
    'group-dots', 'group-drop', 'group-flame', 'group-heart', 'group-leaf', 'group-moon',
    'group-shield', 'group-smile', 'helix', 'home', 'info', 'search', 'upload',
]);

it('switches to the small mark at 24 px and below', function () {
    $small = renderUi('<x-brand.mark :size="20" />');
    $full = renderUi('<x-brand.mark :size="34" />');

    expect($small)->toContain('width="20"')->toContain('stroke-width="5"')->not->toContain('stroke-opacity')
        ->and($full)->toContain('width="34"')->toContain('stroke-opacity')
        ->and(renderUi('<x-brand.mark :size="24" />'))->not->toContain('stroke-opacity');
});

it('renders the lockup', function () {
    expect(renderUi('<x-brand.lockup />'))->toContain('viewBox="0 0 360 76"')->not->toContain('c2pa');
});

it('renders the panel, narrow or not', function () {
    expect(renderUi('<x-ui.panel>Body</x-ui.panel>'))->toContain('max-w-[1080px]')->toContain('Body')
        ->and(renderUi('<x-ui.panel narrow>Body</x-ui.panel>'))->toContain('max-w-[900px]');
});

it('renders the page header with subtitle and actions', function () {
    $html = renderUi('<x-ui.page-header title="Genetics" subtitle="Your variants"><x-slot:actions><b>Go</b></x-slot:actions></x-ui.page-header>');

    expect($html)->toContain('<h1')->toContain('Genetics')->toContain('Your variants')->toContain('<b>Go</b>');
});

it('renders a section with an optional link', function () {
    $with = renderUi('<x-ui.section title="Latest" link-href="/all" link-label="See all">Rows</x-ui.section>');
    $without = renderUi('<x-ui.section title="Latest">Rows</x-ui.section>');

    expect($with)->toContain('Latest')->toContain('href="/all"')->toContain('See all')
        ->and($without)->not->toContain('<a ');
});

it('passes data-stat through on a stat', function () {
    $html = renderUi('<x-ui.stat label="Variants" value="128" caption="in 9 groups" data-stat="variants" />');

    expect($html)->toContain('data-stat="variants"')->toContain('Variants')->toContain('128')->toContain('in 9 groups');
});

it('renders icon tiles by tone and size', function () {
    $html = renderUi('<x-ui.icon-tile tone="coral" :size="44">HL</x-ui.icon-tile>');

    expect($html)->toContain('bg-tone-coral-bg')->toContain('size-11')->toContain('HL');
});

it('renders link rows in both variants', function () {
    $bordered = renderUi('<x-ui.link-row href="/a" meta="Complete" title="Methylfolate" />');
    $divided = renderUi('<x-ui.link-row href="/b" variant="divided" title="Methylation" />');

    expect($bordered)->toContain('href="/a"')->toContain('Complete')->toContain('Methylfolate')->toContain('border-line')
        ->and($divided)->toContain('border-line-soft')->not->toContain('rounded-row');
});

it('renders buttons by variant', function () {
    expect(renderUi('<x-ui.button>Go</x-ui.button>'))->toContain('<button')->toContain('bg-juniper')
        ->and(renderUi('<x-ui.button variant="secondary">Go</x-ui.button>'))->toContain('bg-surface-sunken')
        ->and(renderUi('<x-ui.button variant="on-tint" href="/x">Go</x-ui.button>'))->toContain('<a href="/x"')->toContain('bg-white');
});

it('renders segmented controls and keeps tab roles on x-tabs', function () {
    $segmented = renderUi('<x-ui.segmented><x-ui.segmented.item :active="true">One</x-ui.segmented.item></x-ui.segmented>');
    $tabs = renderUi('<x-tabs><x-tabs.item :active="true">One</x-tabs.item><x-tabs.item>Two</x-tabs.item></x-tabs>');

    expect($segmented)->toContain('role="tablist"')->toContain('role="tab"')->toContain('aria-selected="true"')
        ->and($tabs)->toContain('role="tablist"')->toContain('rounded-full')
        ->and(substr_count($tabs, 'role="tab"'))->toBe(2)
        ->and($tabs)->toContain('aria-selected="true"')->toContain('aria-selected="false"');
});

it('renders chips with pressed state and count', function () {
    $active = renderUi('<x-ui.chip :active="true" :count="12">Methylation</x-ui.chip>');
    $inactive = renderUi('<x-ui.chip>Hormones</x-ui.chip>');

    expect($active)->toContain('aria-pressed="true"')->toContain('>12<')->toContain('bg-juniper')
        ->and($inactive)->toContain('aria-pressed="false"')->not->toContain('tabular');
});

it('renders status tones with their label', function (string $tone, string $dot) {
    expect(renderUi('<x-ui.status tone="'.$tone.'" label="Complete" />'))->toContain('Complete')->toContain($dot);
})->with([['ok', 'bg-ok'], ['warn', 'bg-warn'], ['bad', 'bg-bad'], ['neutral', 'bg-zinc-400'], ['active', 'bg-juniper']]);

it('renders progress steps with the right fill', function () {
    $html = renderUi('<x-ui.progress-steps :step="2" :total="4" label="Searching sources" />');

    expect($html)->toContain('Searching sources · step 2 of 4')->toContain('width: 50%');
});

it('renders evidence grades as words', function (string $grade, string $dot) {
    expect(renderUi('<x-ui.grade grade="'.$grade.'" />'))->toContain($grade)->toContain($dot);
})->with([['strong', 'bg-ok'], ['moderate', 'bg-juniper'], ['limited', 'bg-warn']]);

it('fills the allele that matches the effect allele', function () {
    $html = renderUi('<x-ui.allele-pair :alleles="[\'G\', \'T\']" effect="G" />');

    expect($html)->toContain('aria-label="Genotype G T; one copy of effect allele G"')
        ->and(substr_count($html, 'data-allele'))->toBe(2)
        ->and(substr_count($html, 'data-effect'))->toBe(1);
});

it('draws both alleles outlined for a null effect', function () {
    $html = renderUi('<x-ui.allele-pair :alleles="[\'A\', \'G\']" :effect="null" />');

    expect($html)->toContain('aria-label="Genotype A G"')
        ->and(substr_count($html, 'data-allele'))->toBe(2)
        ->and($html)->not->toContain('data-effect');
});

it('draws two filled or two outlined circles for a homozygous genotype', function () {
    $filled = renderUi('<x-ui.allele-pair :alleles="[\'G\', \'G\']" effect="G" />');
    $outlined = renderUi('<x-ui.allele-pair :alleles="[\'T\', \'T\']" effect="G" />');

    expect(substr_count($filled, 'data-effect'))->toBe(2)
        ->and($filled)->toContain('two copies of effect allele G')
        ->and($outlined)->not->toContain('data-effect')
        ->and($outlined)->toContain('no copies of effect allele G');
});

it('draws a single circle for a one-letter genotype', function () {
    $html = renderUi('<x-ui.allele-pair :alleles="[\'D\']" effect="D" />');

    expect(substr_count($html, 'data-allele'))->toBe(1)
        ->and($html)->toContain('aria-label="Genotype D; one copy of effect allele D"');
});

it('renders the copies label', function () {
    expect(renderUi('<x-ui.copies :count="1" />'))->toContain('1 COPY')
        ->and(renderUi('<x-ui.copies :count="2" />'))->toContain('2 COPIES');
});

it('renders a notice with the info icon', function () {
    $html = renderUi('<x-ui.notice>Not medical advice.</x-ui.notice>');

    expect($html)->toContain('Not medical advice.')->toContain('bg-lime-tint')->toContain('<svg');
});

it('renders a dropzone around a file input that keeps its wire:model', function () {
    $html = renderUi('<x-ui.dropzone wire:model="file" label="Drop a file" help="Up to 25 MB" filename="markers.csv" />');

    expect($html)->toContain('<label')->toContain('type="file"')->toContain('wire:model="file"')
        ->toContain('Drop a file')->toContain('Up to 25 MB')->toContain('markers.csv')->toContain('Choose file');
});
