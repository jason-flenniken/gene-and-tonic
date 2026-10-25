<?php

use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Modules\Genetics\Actions\LookUpAll;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Enums\VariantGroup;
use Modules\Genetics\Support\VariantGrouping;
use Modules\Shared\Contracts\VariantLinks;

new class extends Component
{
    /**
     * Computed, not public, so genotypes never land in the component snapshot. Empty for guests.
     *
     * @return array{groups: list<array{group: VariantGroup, count: int, linked: int}>, waiting: int, total: int}
     */
    #[Computed]
    public function summary(): array
    {
        $userId = auth()->id();

        if ($userId === null) {
            return ['groups' => [], 'waiting' => 0, 'total' => 0];
        }

        $variants = app(GeneticProfileReader::class)->forUser((int) $userId)->variants;
        $links = app(VariantLinks::class)->forUser((int) $userId);
        $counts = (new VariantGrouping($variants, $links))->counts();

        $linked = [];

        foreach ($variants as $variant) {
            $group = VariantGrouping::groupOf($variant);

            if ($group !== null && ($links[$variant->rsid] ?? []) !== []) {
                $linked[$group->value] = ($linked[$group->value] ?? 0) + 1;
            }
        }

        $groups = [];

        foreach (VariantGroup::cases() as $case) {
            if (isset($counts['groups'][$case->value])) {
                $groups[] = ['group' => $case, 'count' => $counts['groups'][$case->value], 'linked' => $linked[$case->value] ?? 0];
            }
        }

        usort($groups, fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['groups' => array_slice($groups, 0, 5), 'waiting' => $counts['unlooked'], 'total' => $counts['all']];
    }

    /**
     * Queues lookups for the signed-in user's own pending and failed variants and toasts the outcome.
     */
    public function lookUpAll(LookUpAll $lookUpAll): void
    {
        $userId = auth()->id();
        abort_if($userId === null, 401);

        $result = $lookUpAll->handle($userId);

        unset($this->summary);

        if ($result->limited) {
            Flux::toast(text: 'Too many requests, try again in a minute.', variant: 'danger');
        } elseif ($result->nothingPending || $result->queued === 0) {
            Flux::toast(text: 'Nothing to look up.');
        } else {
            Flux::toast(text: $result->queued.' '.str('variant')->plural($result->queued).' queued for lookup.', variant: 'success');
        }
    }
};
?>

<div class="contents">
    @guest
        <x-ui.section title="Your genes" data-section="your-genes">
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-row bg-juniper-tint px-4 py-3.5" data-genes="sign-in">
                <p class="text-[15px] font-semibold text-ink">Sign in to see your genes</p>
                <x-ui.button :href="route('login')">Sign in</x-ui.button>
            </div>
        </x-ui.section>
    @else
        <x-ui.section title="Your genes" :link-href="route('genetics.profile')" link-label="Open genetics" data-section="your-genes">
            @if ($this->summary['total'] === 0)
                <x-ui.notice>
                    No genetic results yet. <a href="{{ route('genetics.profile') }}" class="font-semibold text-juniper hover:text-juniper-hover">Add your genetic results</a> to group your variants here.
                </x-ui.notice>
            @else
                <div class="flex flex-col">
                    @foreach ($this->summary['groups'] as $row)
                        @php($group = $row['group'])
                        <a wire:key="gene-group-{{ $group->value }}" href="{{ route('genetics.profile', ['group' => $group->value]) }}" data-gene-group="{{ $group->value }}" class="flex min-h-16 w-full items-center gap-3.5 border-b border-line-soft py-2 text-ink hover:bg-surface-muted">
                            <x-ui.icon-tile :tone="$group->tone()"><x-dynamic-component :component="'icon.'.$group->icon()" /></x-ui.icon-tile>
                            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="text-base font-medium">{{ $group->label() }}</span>
                                <span class="text-[13px] text-ink-subtle">{{ $row['count'].' '.str('variant')->plural($row['count']).($row['linked'] > 0 ? ' · '.$row['linked'].' linked' : '') }}</span>
                            </span>
                            <x-icon.chevron-right class="size-4 shrink-0 text-ink-subtle" />
                        </a>
                    @endforeach
                </div>
                @if ($this->summary['waiting'] > 0)
                    <div class="mt-2 flex flex-wrap items-center justify-between gap-3 rounded-row bg-lime-tint px-4 py-3" data-lookup-banner>
                        <span class="text-[14px] text-ink-body">{{ $this->summary['waiting'] }} {{ str('variant')->plural($this->summary['waiting']) }} {{ $this->summary['waiting'] === 1 ? 'is' : 'are' }} waiting for a lookup</span>
                        <x-ui.button variant="on-tint" class="!h-[38px] !px-4 !text-[14px]" wire:click="lookUpAll">Look up now</x-ui.button>
                    </div>
                @endif
            @endif
        </x-ui.section>
    @endguest
</div>
