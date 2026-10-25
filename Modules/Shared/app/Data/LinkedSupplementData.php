<?php

namespace Modules\Shared\Data;

use Illuminate\Contracts\Support\Arrayable;
use Modules\Shared\Data\Concerns\ArrayableData;

/**
 * A supplement a variant links to. Holds no user data.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class LinkedSupplementData implements Arrayable
{
    use ArrayableData;

    public function __construct(
        public int $supplementId,
        public string $name,
    ) {}
}
