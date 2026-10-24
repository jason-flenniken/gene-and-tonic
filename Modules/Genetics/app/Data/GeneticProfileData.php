<?php

namespace Modules\Genetics\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class GeneticProfileData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<VariantData>  $variants
     */
    public function __construct(
        public int $userId,
        public array $variants,
    ) {}

    public function isEmpty(): bool
    {
        return $this->variants === [];
    }

    public function count(): int
    {
        return count($this->variants);
    }
}
