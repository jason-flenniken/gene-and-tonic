<?php

use Livewire\Livewire;
use Modules\Intelligence\Models\AiRequest;

beforeEach(function () {
    aiRequest(['model' => 'recent-model', 'input_tokens' => 111, 'estimated_cost_usd' => 0.2]);
    aiRequest(['model' => 'old-model', 'input_tokens' => 222, 'estimated_cost_usd' => 0.1, 'status' => 'failed', 'error' => 'boom'])
        ->forceFill(['created_at' => now()->subDays(3)])->save();
});

it('switches every number with the window', function () {
    Livewire::test('intelligence::usage-log', ['window' => '24h'])
        ->assertSeeHtml('data-stat="calls"')
        ->assertSee('recent-model')
        ->assertDontSee('old-model')
        ->assertDontSee('failed')
        ->assertSee('$0.2000')
        ->set('window', '7d')
        ->assertSee('old-model')
        ->assertSee('1 failed')
        ->assertSee('$0.3000')
        ->set('window', 'all')
        ->assertSee('333');
});

it('counts calls per window', function () {
    $count = fn (string $window) => Livewire::test('intelligence::usage-log', ['window' => $window])
        ->assertSeeInOrder(['Calls', '1'])
        ->html();

    expect($count('24h'))->toContain('recent-model')->not->toContain('old-model')
        ->and($count('all'))->toContain('old-model');
});

it('falls back to all time for a bogus window', function () {
    Livewire::withQueryParams(['window' => 'bogus'])
        ->test('intelligence::usage-log')
        ->assertOk()
        ->assertSee('old-model')
        ->assertSee('recent-model');
});

it('shows the failed caption only when there are failures', function () {
    Livewire::test('intelligence::usage-log', ['window' => '24h'])->assertDontSee('failed');
    Livewire::test('intelligence::usage-log', ['window' => 'all'])->assertSee('1 failed');
});

it('shows labelled cost by task rows and the web search credits row', function () {
    aiRequest(['operation' => 'search', 'provider' => 'firecrawl', 'model' => null, 'profile' => null, 'effort' => null, 'credits' => 8, 'estimated_cost_usd' => 0.05]);

    Livewire::test('intelligence::usage-log')
        ->assertSee('Cost by task')
        ->assertSee('Web search · 8 credits')
        ->assertSeeHtml('data-task-row')
        ->assertSee('$0.3000')
        ->assertSee('About $');
});

it('renders unknown and null profiles without error', function () {
    aiRequest(['profile' => 'ghost-profile']);
    aiRequest(['profile' => null]);

    Livewire::test('intelligence::usage-log')->assertOk()->assertSee('ghost-profile')->assertSee('Unknown');
});

it('puts the table in its own scrolling box with every column', function () {
    Livewire::test('intelligence::usage-log')
        ->assertSeeHtml('overflow-x-auto')
        ->assertSeeInOrder(['Time', 'Task', 'Tokens in / out', 'Latency', 'Cost', 'Status'])
        ->assertSee('1,200 ms')
        ->assertSee('OK')
        ->assertSee('FAILED');
});

it('shows the empty state per window', function () {
    AiRequest::query()->delete();

    Livewire::test('intelligence::usage-log', ['window' => '24h'])->assertSee('No AI calls recorded yet.');
});

it('serves the page with the window query string', function () {
    $this->withoutVite();

    $this->get('/usage?window=7d')->assertOk()->assertSee('AI usage');
});
