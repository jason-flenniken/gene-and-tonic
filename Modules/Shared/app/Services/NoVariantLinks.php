<?php

namespace Modules\Shared\Services;

use Modules\Shared\Contracts\VariantLinks;

/**
 * The default until a module that knows about supplements binds its own.
 */
final class NoVariantLinks implements VariantLinks
{
    public function forUser(int $userId): array
    {
        return [];
    }
}
