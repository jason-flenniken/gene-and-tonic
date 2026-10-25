<?php

namespace Modules\Intelligence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Intelligence\Models\AiRequest;

/** @extends Factory<AiRequest> */
class AiRequestFactory extends Factory
{
    protected $model = AiRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'operation' => 'generate',
            'profile' => 'supplements.research',
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
        ];
    }

    public function failed(string $error = 'boom'): static
    {
        return $this->state(['status' => 'failed', 'error' => $error]);
    }

    public function search(int $credits = 8): static
    {
        return $this->state([
            'operation' => 'search',
            'profile' => null,
            'provider' => 'firecrawl',
            'model' => null,
            'effort' => null,
            'credits' => $credits,
            'input_tokens' => 0,
            'output_tokens' => 0,
        ]);
    }

    public function ago(\DateTimeInterface|string $when): static
    {
        $at = is_string($when) ? now()->sub($when) : $when;

        return $this->state(['created_at' => $at, 'updated_at' => $at]);
    }
}
