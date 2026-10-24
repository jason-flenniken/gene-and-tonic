<?php

namespace Modules\Intelligence\Data;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class PromptRequest implements Arrayable
{
    use ArrayableData;

    /**
     * @param  array<string, mixed>  $variables  Passed to the profile's instruction and prompt views.
     * @param  (Closure(JsonSchema): array<string, mixed>)|null  $schema
     */
    public function __construct(
        public string $profile,
        public array $variables = [],
        public ?Closure $schema = null,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @param  (Closure(JsonSchema): array<string, mixed>)|null  $schema
     */
    public static function for(string $profile, array $variables = [], ?Closure $schema = null): self
    {
        return new self($profile, $variables, $schema);
    }
}
