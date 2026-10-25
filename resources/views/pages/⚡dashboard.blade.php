<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Supplements\Contracts\SupplementResearchReader;

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

    #[Computed]
    public function greeting(): string
    {
        $user = auth()->user();

        if ($user === null) {
            return 'Welcome to '.config('app.name');
        }

        $period = match (true) {
            now()->hour < 12 => 'morning',
            now()->hour < 18 => 'afternoon',
            default => 'evening',
        };

        $firstName = trim(explode(' ', trim($user->name))[0]);

        return $firstName === '' ? "Good {$period}" : "Good {$period}, {$firstName}";
    }

    #[Computed]
    public function subtitle(): string
    {
        $supplements = app(SupplementResearchReader::class)->count();
        $parts = [now()->format('l, F j'), $supplements.' '.str('supplement')->plural($supplements)];

        $userId = auth()->id();

        if ($userId !== null) {
            $variants = app(GeneticProfileReader::class)->forUser((int) $userId)->count();
            $parts[] = $variants.' '.str('variant')->plural($variants);
        }

        return implode(' · ', $parts);
    }
};
?>

<div class="mx-auto flex w-full max-w-[1080px] flex-col gap-7 rounded-panel border border-line bg-surface p-[clamp(18px,3vw,36px)]" data-panel="overview">
    {{-- panel:overview --}}
    <x-ui.page-header :title="$this->greeting" :subtitle="$this->subtitle" />

    <livewire:recommendations::top-match />

    <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))]">
        <livewire:supplements::stats />
        @auth
            <livewire:genetics::variant-stat />
            <livewire:recommendations::match-stat />
        @endauth
        <livewire:intelligence::spend-stat />
    </div>
</div>
