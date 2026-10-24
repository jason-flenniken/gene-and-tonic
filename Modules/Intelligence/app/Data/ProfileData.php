<?php

namespace Modules\Intelligence\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class ProfileData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  view-string  $instructionsView
     * @param  view-string  $promptView
     */
    public function __construct(
        public string $name,
        public string $provider,
        public string $model,
        public ?string $effort,
        public int $maxTokens,
        public int $timeout,
        public string $instructionsView,
        public string $promptView,
    ) {}
}
