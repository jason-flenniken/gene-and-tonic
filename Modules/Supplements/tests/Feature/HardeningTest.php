<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Jobs\RunSupplementResearch;
use Modules\Supplements\Models\Supplement;
use Modules\Supplements\Models\SupplementResearch;

beforeEach(function () {
    $this->withoutVite();
    Queue::fake();
});

const RATE_MESSAGE = 'Too many requests, try again in a minute.';

it('refuses the 11th supplement add in a minute and creates nothing', function () {
    $component = Livewire::test('supplements::catalog');

    foreach (range(1, 10) as $i) {
        $component->set('name', "Supplement {$i}")->call('add')->assertHasNoErrors();
    }

    $component->set('name', 'Supplement 11')->call('add')->assertHasErrors(['name'])->assertSee(RATE_MESSAGE);

    expect(Supplement::count())->toBe(10);
});

it('refuses the 11th retry in a minute', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Failed]);
    $component = Livewire::test('supplements::catalog');

    foreach (range(1, 10) as $i) {
        $component->call('retry', $supplement->id);
        SupplementResearch::query()->update(['status' => ResearchStatus::Failed]);
    }
    Queue::assertPushed(RunSupplementResearch::class, 10);

    $component->call('retry', $supplement->id)->assertDispatched('toast-show', fn ($event, $params) => str_contains(json_encode($params), RATE_MESSAGE));

    Queue::assertPushed(RunSupplementResearch::class, 10);
});

it('refuses the 11th re-run from the supplement page', function () {
    $supplement = Supplement::factory()->create();
    SupplementResearch::factory()->for($supplement)->create(['status' => ResearchStatus::Failed]);
    $component = Livewire::test('supplements::show', ['supplement' => $supplement->id]);

    foreach (range(1, 10) as $i) {
        $component->call('rerun');
        SupplementResearch::query()->update(['status' => ResearchStatus::Failed]);
    }
    Queue::assertPushed(RunSupplementResearch::class, 10);

    $component->call('rerun')->assertDispatched('toast-show', fn ($event, $params) => str_contains(json_encode($params), RATE_MESSAGE));

    Queue::assertPushed(RunSupplementResearch::class, 10);
});

it('leaves a fresh active run alone and fails a stale one', function () {
    $fresh = SupplementResearch::factory()->create(['status' => ResearchStatus::Searching, 'queued_at' => now()->subMinutes(2), 'started_at' => now()->subMinutes(1)]);
    $stale = SupplementResearch::factory()->create(['status' => ResearchStatus::Searching, 'queued_at' => now()->subMinutes(21), 'started_at' => now()->subMinutes(20)]);
    $staleQueued = SupplementResearch::factory()->create(['status' => ResearchStatus::Queued, 'queued_at' => now()->subMinutes(30)]);
    $complete = SupplementResearch::factory()->create(['status' => ResearchStatus::Complete, 'queued_at' => now()->subHours(2)]);

    $this->artisan('supplements:reap-stale')->assertSuccessful();

    expect($fresh->fresh()->status)->toBe(ResearchStatus::Searching)
        ->and($stale->fresh()->status)->toBe(ResearchStatus::Failed)
        ->and($stale->fresh()->error)->toBe('Timed out, try again.')
        ->and($staleQueued->fresh()->status)->toBe(ResearchStatus::Failed)
        ->and($complete->fresh()->status)->toBe(ResearchStatus::Complete);
});

it('schedules the reaper every five minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'supplements:reap-stale'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/5 * * * *');
});
