<?php

namespace Modules\Shared\Data\Concerns;

use BackedEnum;
use Illuminate\Contracts\Support\Arrayable;

trait ArrayableData
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_map(self::normalize(...), get_object_vars($this));
    }

    private static function normalize(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof Arrayable => $value->toArray(),
            is_array($value) => array_map(self::normalize(...), $value),
            default => $value,
        };
    }
}
