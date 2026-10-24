<?php

namespace Modules\Intelligence\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class GenerationResult implements Arrayable
{
    use ArrayableData;

    /**
     * @param  array<string, mixed>  $structured
     */
    public function __construct(
        public string $text,
        public array $structured,
        public string $provider,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
    ) {}
}
