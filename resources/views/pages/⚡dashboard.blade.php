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
            <flux:card>
                <flux:text>Overview will appear here.</flux:text>
            </flux:card>
        </div>
    @elseif ($tab === 'supplements')
        <div role="tabpanel" data-panel="supplements">
            {{-- panel:supplements --}}
            <flux:card>
                <flux:text>Supplement research will appear here.</flux:text>
            </flux:card>
        </div>
    @else
        <div role="tabpanel" data-panel="usage">
            {{-- panel:usage --}}
            <flux:card>
                <flux:text>AI usage will appear here.</flux:text>
            </flux:card>
        </div>
    @endif
</div>
