<?php

namespace Modules\Intelligence\Exceptions;

use RuntimeException;

class UnknownProfileException extends RuntimeException
{
    public static function named(string $name): self
    {
        return new self("No AI profile named [{$name}] is configured.");
    }
}
