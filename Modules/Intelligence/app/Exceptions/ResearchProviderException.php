<?php

namespace Modules\Intelligence\Exceptions;

use RuntimeException;

/**
 * Carries a fixed, readable message that never includes keys or response bodies.
 * Deliberately has no previous exception: report() would log the chain.
 */
class ResearchProviderException extends RuntimeException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
