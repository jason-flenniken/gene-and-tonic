<?php

use Livewire\Livewire;
use Modules\Intelligence\Models\AiRequest;
use Modules\Intelligence\Repositories\AiRequestRepository;

function aiRequest(array $overrides = []): AiRequest
{
    return AiRequest::query()->create([
        'operation' => 'generate',
        'profile' => 'research',
        'provider' => 'openai',
        'model' => 'gpt-6.1-sol',
        'effort' => 'medium',
        'input_tokens' => 100,
        'output_tokens' => 50,
        'credits' => null,
        'latency_ms' => 1200,
        'estimated_cost_usd' => 0.01,
        'status' => 'success',
        'error' => null,
        ...$overrides,
    ]);
}

it('summarises totals with failures and the last 24 hours', function () {
    aiRequest(['input_tokens' => 1000, 'output_tokens' => 200, 'estimated_cost_usd' => 0.5]);
    aiRequest(['status' => 'failed', 'error' => 'boom', 'input_tokens' => 10, 'output_tokens' => 0, 'estimated_cost_usd' => 0.25]);
    aiRequest(['estimated_cost_usd' => 1])->forceFill(['created_at' => now()->subDays(3)])->save();

    $summary = app(AiRequestRepository::class)->summary();

    expect($summary->calls)->toBe(3)
        ->and($summary->failedCalls)->toBe(1)
        ->and($summary->inputTokens)->toBe(1110)
        ->and($summary->outputTokens)->toBe(250)
        ->and($summary->estimatedCostUsd)->toBe(1.75)
        ->and($summary->callsLast24h)->toBe(2)
        ->and($summary->costLast24hUsd)->toBe(0.75);
});

it('returns zeros for an empty table', function () {
    $summary = app(AiRequestRepository::class)->summary();

    expect($summary->calls)->toBe(0)
        ->and($summary->estimatedCostUsd)->toBe(0.0)
        ->and($summary->costLast24hUsd)->toBe(0.0);
});

it('renders the usage summary tiles', function () {
    aiRequest(['input_tokens' => 12345, 'estimated_cost_usd' => 0.1234]);

    Livewire::test('intelligence::usage-summary')
        ->assertSee('12,345')
        ->assertSee('$0.1234')
        ->assertSee('0 failed');
});

it('lists requests newest first, 20 per page', function () {
    foreach (range(1, 25) as $i) {
        aiRequest(['model' => "model-{$i}"]);
    }

    Livewire::test('intelligence::usage-log')
        ->assertSeeInOrder(['model-25', 'model-24', 'model-6'])
        ->assertSee('Estimated cost')
        ->call('gotoPage', 2)
        ->assertSee('model-5')
        ->assertSee('model-1')
        ->assertDontSee('model-6');
});

it('shows credits for search rows and an escaped truncated error for failures', function () {
    aiRequest(['operation' => 'search', 'provider' => 'firecrawl', 'model' => null, 'profile' => null, 'effort' => null, 'credits' => 8, 'input_tokens' => 0, 'output_tokens' => 0]);
    aiRequest(['status' => 'failed', 'error' => '<script>alert(1)</script>']);

    Livewire::test('intelligence::usage-log')
        ->assertSee('8 credits')
        ->assertSee('failed')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;');
});

it('shows an empty state without rows', function () {
    Livewire::test('intelligence::usage-log')->assertSee('No AI calls recorded yet.');
});

it('renders the usage page', function () {
    $this->withoutVite();
    aiRequest(['model' => 'dash-model']);

    $this->get('/usage')->assertOk()->assertSee('dash-model')->assertSee('AI usage');
});
