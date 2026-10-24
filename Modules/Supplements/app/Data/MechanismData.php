<?php

namespace Modules\Supplements\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class MechanismData implements Arrayable
{
    use ArrayableData;

    /**
     * @param  list<string>  $sourceKeys
     */
    public function __construct(
        public string $description,
        public array $sourceKeys,
    ) {}
}
