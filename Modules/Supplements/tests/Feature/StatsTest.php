<?php

use Livewire\Livewire;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

function researched(string $name, ResearchStatus $status): Supplement
{
    $supplement = Supplement::factory()->create(['name' => $name, 'normalized_name' => mb_strtolower($name), 'slug' => str($name)->slug()]);
    SupplementResearch::factory()->create(['supplement_id' => $supplement->id, 'status' => $status]);

    return $supplement;
}

it('shows the total captioned Research complete when nothing is running', function () {
    researched('Zinc', ResearchStatus::Complete);
    researched('Broken one', ResearchStatus::Failed);
    Supplement::factory()->create();

    Livewire::test('supplements::stats')
        ->assertSee('Supplements')
        ->assertSeeText('3')
        ->assertSee('Research complete')
        ->assertDontSee('in progress');
});

it('counts queued, searching and analyzing runs as in progress', function () {
    researched('Done', ResearchStatus::Complete);
    researched('Waiting', ResearchStatus::Queued);
    researched('Reading', ResearchStatus::Searching);
    researched('Thinking', ResearchStatus::Analyzing);

    Livewire::test('supplements::stats')
        ->assertSeeText('4')
        ->assertSee('3 in progress')
        ->assertDontSee('Research complete');
});

it('renders as a single stat card', function () {
    researched('Zinc', ResearchStatus::Complete);

    expect(substr_count(Livewire::test('supplements::stats')->html(), 'data-stat='))->toBe(1);
});
