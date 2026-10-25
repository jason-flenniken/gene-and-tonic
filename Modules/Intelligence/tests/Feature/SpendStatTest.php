<?php

use Livewire\Livewire;
use Modules\Intelligence\Models\AiRequest;

function spendRow(float $cost, $createdAt = null): void
{
    $row = AiRequest::query()->create([
        'operation' => 'generate', 'provider' => 'openai', 'input_tokens' => 1, 'output_tokens' => 1,
        'latency_ms' => 1, 'estimated_cost_usd' => $cost, 'status' => 'success',
    ]);

    if ($createdAt !== null) {
        $row->forceFill(['created_at' => $createdAt])->save();
    }
}

it('shows the last 24 hours of cost to two decimals with the call count', function () {
    spendRow(0.123);
    spendRow(0.4);
    spendRow(9.0, now()->subDays(3));

    Livewire::test('intelligence::spend-stat')
        ->assertSee('AI spend')
        ->assertSee('$0.52')
        ->assertSee('2 calls in 24h');
});

it('shows zero spend with no calls', function () {
    Livewire::test('intelligence::spend-stat')->assertSee('$0.00')->assertSee('0 calls in 24h');
});

it('uses the singular for one call', function () {
    spendRow(0.01);

    Livewire::test('intelligence::spend-stat')->assertSee('1 call in 24h');
});
