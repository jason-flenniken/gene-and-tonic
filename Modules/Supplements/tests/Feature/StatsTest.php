<?php

use Livewire\Livewire;
use Modules\Intelligence\Models\AiRequest;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

function researched(string $name, ResearchStatus $status, $completedAt = null): Supplement
{
    $supplement = Supplement::factory()->create(['name' => $name, 'normalized_name' => mb_strtolower($name), 'slug' => str($name)->slug()]);
    SupplementResearch::factory()->create([
        'supplement_id' => $supplement->id,
        'status' => $status,
        'completed_at' => $completedAt,
    ]);

    return $supplement;
}

it('shows total, per-status counts and the 5 latest completed researches', function () {
    foreach (range(1, 6) as $i) {
        researched("Done {$i}", ResearchStatus::Complete, now()->subDays(10 - $i));
    }
    researched('Broken one', ResearchStatus::Failed);
    researched('Waiting one', ResearchStatus::Queued);
    researched('Waiting two', ResearchStatus::Queued);
    Supplement::factory()->create();

    $component = Livewire::test('supplements::stats')
        ->assertSeeText('10')
        ->assertSeeText('Complete: 6')
        ->assertSeeText('Failed: 1')
        ->assertSeeText('Queued: 2')
        ->assertSeeText('Searching: 0')
        ->assertSeeText('Analyzing: 0')
        ->assertSee('Done 6')
        ->assertSee('Done 2')
        ->assertDontSee('Done 1<');

    $first = Supplement::query()->where('name', 'Done 6')->firstOrFail();
    $component->assertSeeHtml('href="'.route('supplements.show', $first->id).'"');
    expect($component->html())->not->toContain('>Done 1<');
});

it('shows an empty state when nothing is complete', function () {
    Livewire::test('supplements::stats')
        ->assertSee('No completed research yet.')
        ->assertSeeText('Complete: 0');
});

it('renders both widgets on the overview tab', function () {
    $this->withoutVite();
    researched('Zinc', ResearchStatus::Complete, now());
    AiRequest::query()->create([
        'operation' => 'generate', 'provider' => 'openai', 'input_tokens' => 4321, 'output_tokens' => 1,
        'latency_ms' => 1, 'estimated_cost_usd' => 0.5, 'status' => 'success',
    ]);

    $this->get('/?tab=overview')
        ->assertOk()
        ->assertSee('Zinc')
        ->assertSee('Total supplements')
        ->assertSee('4,321')
        ->assertSee('$0.5000');
});
