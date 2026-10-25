@props(['topics' => [], 'active' => '', 'total' => 0])

{{-- $topics is a list of ['value' => string, 'label' => string, 'count' => int]; empty means no chips. --}}
@if ($topics !== [])
    <div {{ $attributes->class('flex flex-wrap gap-2') }} data-effect-chips>
        <x-ui.chip :active="$active === ''" :count="$total" wire:click="$set('effectTopic', '')">All</x-ui.chip>
        @foreach ($topics as $topic)
            <x-ui.chip :active="$active === $topic['value']" :count="$topic['count']" wire:key="chip-{{ $topic['value'] }}" wire:click="$set('effectTopic', '{{ $topic['value'] }}')">{{ $topic['label'] }}</x-ui.chip>
        @endforeach
    </div>
@endif
