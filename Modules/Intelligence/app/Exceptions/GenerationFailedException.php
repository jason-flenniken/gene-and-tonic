<?php

namespace Modules\Intelligence\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Carries a readable message that is safe to show or log: it never includes keys, prompts or response bodies.
 */
class GenerationFailedException extends RuntimeException
{
    public static function because(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
