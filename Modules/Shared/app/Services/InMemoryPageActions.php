<?php

namespace Modules\Shared\Services;

use Modules\Shared\Contracts\PageActions;

final class InMemoryPageActions implements PageActions
{
    /**
     * @var array<string, list<string>>
     */
    private array $viewsBySlot = [];

    public function register(string $slot, string $view): void
    {
        $views = $this->viewsBySlot[$slot] ?? [];

        if (in_array($view, $views, true)) {
            return;
        }

        $views[] = $view;
        $this->viewsBySlot[$slot] = $views;
    }

    public function for(string $slot): array
    {
        return $this->viewsBySlot[$slot] ?? [];
    }
}
