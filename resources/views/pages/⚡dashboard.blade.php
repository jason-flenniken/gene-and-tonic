<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Home')] class extends Component
{
    private const TAB_ROUTES = [
        'supplements' => 'supplements.index',
        'genetics' => 'genetics.profile',
        'usage' => 'usage',
    ];

    public function mount(): void
    {
        $tab = request()->query('tab');

        if (is_string($tab) && isset(self::TAB_ROUTES[$tab])) {
            $this->redirectRoute(self::TAB_ROUTES[$tab], navigate: false);
        }
    }
};
?>

<div class="space-y-6" data-panel="overview">
    {{-- panel:overview --}}
    <div class="grid gap-6 md:grid-cols-2">
        <livewire:supplements::stats />
        <livewire:intelligence::usage-summary />
    </div>
</div>
