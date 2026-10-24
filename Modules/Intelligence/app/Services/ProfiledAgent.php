<?php

namespace Modules\Intelligence\Services;

use Closure;
use Laravel\Ai\Attributes\Strict;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\StructuredAnonymousAgent;

/**
 * Structured agent carrying the profile's reasoning effort and max output tokens,
 * which laravel/ai only reads from the agent itself.
 */
#[Strict]
class ProfiledAgent extends StructuredAnonymousAgent implements HasProviderOptions
{
    /**
     * @param  iterable<mixed>  $messages
     * @param  iterable<mixed>  $tools
     */
    public function __construct(
        string $instructions,
        iterable $messages = [],
        iterable $tools = [],
        ?Closure $schema = null,
        private readonly ?string $effort = null,
        private readonly int $maxTokens = 4096,
    ) {
        parent::__construct($instructions, $messages, $tools, $schema);
    }

    public function maxTokens(): int
    {
        return $this->maxTokens;
    }

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $name = $provider instanceof Lab ? $provider->value : $provider;

        if ($name !== Lab::OpenAI->value || $this->effort === null) {
            return [];
        }

        return ['reasoning' => ['effort' => $this->effort]];
    }
}
