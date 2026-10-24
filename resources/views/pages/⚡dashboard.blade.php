<?php

use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    private const TABS = ['overview', 'supplements', 'usage'];

    #[Url(as: 'tab', except: 'overview')]
    public string $tab = 'overview';

    public function mount(): void
    {
        $this->normalizeTab();
    }

    public function updatedTab(): void
    {
        $this->normalizeTab();
    }

    private function normalizeTab(): void
    {
        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'overview';
        }
    }
};
?>

<div class="space-y-6">
    <x-tabs>
        <x-tabs.item :active="$tab === 'overview'" wire:click="$set('tab', 'overview')">Overview</x-tabs.item>
        <x-tabs.item :active="$tab === 'supplements'" wire:click="$set('tab', 'supplements')">Supplements</x-tabs.item>
        <x-tabs.item :active="$tab === 'usage'" wire:click="$set('tab', 'usage')">AI Usage</x-tabs.item>
    </x-tabs>

    @if ($tab === 'overview')
        <div role="tabpanel" data-panel="overview">
            {{-- panel:overview --}}
            <div class="grid gap-6 md:grid-cols-2">
                <livewire:supplements::stats />
                <livewire:intelligence::usage-summary />
            </div>
        </div>
    @elseif ($tab === 'supplements')
        <div role="tabpanel" data-panel="supplements">
            {{-- panel:supplements --}}
            <livewire:supplements::catalog />
        </div>
    @else
        <div role="tabpanel" data-panel="usage">
            {{-- panel:usage --}}
            <livewire:intelligence::usage-log />
        </div>
    @endif
</div>
