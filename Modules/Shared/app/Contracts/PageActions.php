<?php

namespace Modules\Shared\Contracts;

interface PageActions
{
    /**
     * Register a view under a named slot. Registering the same view twice in a slot is a no-op.
     */
    public function register(string $slot, string $view): void;

    /**
     * The views registered for a slot, in registration order.
     *
     * @return list<string>
     */
    public function for(string $slot): array;
}
