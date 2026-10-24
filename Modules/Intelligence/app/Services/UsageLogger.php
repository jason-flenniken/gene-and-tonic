<?php

namespace Modules\Intelligence\Services;

use Modules\Intelligence\Data\GenerationResult;
use Modules\Intelligence\Data\ProfileData;
use Modules\Intelligence\Repositories\AiRequestRepository;
use Throwable;

/**
 * Writes one ai_requests row per provider call. Stores metadata only: never prompts,
 * instructions, response bodies or keys.
 */
class UsageLogger
{
    public function __construct(private readonly AiRequestRepository $requests) {}

    public function logGeneration(ProfileData $profile, ?GenerationResult $result, ?Throwable $error, int $latencyMs): void
    {
        if (! config('intelligence.log_requests', true)) {
            return;
        }

        $model = $result->model ?? $profile->model;
        $inputTokens = $result->inputTokens ?? 0;
        $outputTokens = $result->outputTokens ?? 0;

        $this->requests->record([
            'operation' => 'generate',
            'profile' => $profile->name,
            'provider' => $result->provider ?? $profile->provider,
            'model' => $model,
            'effort' => $profile->effort,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'credits' => null,
            'latency_ms' => $latencyMs,
            'estimated_cost_usd' => $this->estimateCost($model, $inputTokens, $outputTokens),
            'status' => $error === null ? 'success' : 'failed',
            'error' => $error === null ? null : mb_substr($this->scrub($error->getMessage()), 0, 500),
        ]);
    }

    public function estimateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        // Model names contain dots, so index the array instead of using a dotted config path.
        $price = config('intelligence.pricing.models', [])[$model] ?? null;

        if (! is_array($price)) {
            return 0.0;
        }

        $cost = ($inputTokens * (float) ($price['input'] ?? 0) + $outputTokens * (float) ($price['output'] ?? 0)) / 1_000_000;

        return round($cost, 6);
    }

    /**
     * Remove configured provider keys, and anything shaped like one, from a message.
     */
    public function scrub(string $message): string
    {
        $providers = config('ai.providers', []);

        foreach (is_array($providers) ? $providers : [] as $provider) {
            $key = is_array($provider) ? ($provider['key'] ?? null) : null;

            if (is_string($key) && $key !== '') {
                $message = str_replace($key, '[redacted]', $message);
            }
        }

        return (string) preg_replace('/\b(?:sk|key|fc)-[A-Za-z0-9_\-]{8,}/', '[redacted]', $message);
    }
}
