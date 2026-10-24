<?php

namespace Modules\Supplements\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Applies a named limiter (registered in the module provider) to the current request's IP.
 */
final class ActionThrottle
{
    public const MESSAGE = 'Too many requests, try again in a minute.';

    /** Records an attempt. Returns false, without recording, when the limit is already reached. */
    public static function attempt(string $limiter): bool
    {
        $limit = RateLimiter::limiter($limiter)(request());

        return RateLimiter::attempt(
            $limiter.':'.$limit->key,
            $limit->maxAttempts,
            fn (): bool => true,
            $limit->decaySeconds,
        );
    }
}
