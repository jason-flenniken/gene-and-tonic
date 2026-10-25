@props(['effect', 'sources' => [], 'detail' => false])

@php
    $tones = [
        'methylation' => 'green', 'heart' => 'rose', 'mood' => 'peach', 'sleep' => 'indigo', 'stress' => 'coral',
        'energy' => 'butter', 'focus' => 'blue', 'blood' => 'coral', 'immune' => 'teal', 'digestion' => 'leaf',
    ];
    $icons = [
        'methylation' => 'helix', 'heart' => 'group-heart', 'mood' => 'group-smile', 'sleep' => 'group-moon', 'stress' => 'group-leaf',
        'energy' => 'group-flame', 'focus' => 'group-smile', 'blood' => 'group-drop', 'immune' => 'group-shield', 'digestion' => 'group-leaf',
    ];
    $icon = 'icon.'.($icons[$effect->category?->value] ?? 'capsule');
    $title = filled($effect->title) ? $effect->title : $effect->claim;
    $hasTitle = filled($effect->title);
@endphp

@if ($detail)
    <div {{ $attributes->class('rounded-row border border-line px-4 py-3.5') }} data-effect>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="text-[15px] font-semibold text-ink">{{ $title }}</h3>
                @if ($effect->category)
                    <span class="text-[13px] text-ink-subtle">{{ $effect->category->label() }}</span>
                @endif
            </div>
            <x-ui.grade :grade="$effect->evidenceGrade->value" />
        </div>
        @if ($hasTitle)
            <p class="mt-1.5 text-[14px] leading-normal text-ink-body">{{ $effect->claim }}</p>
        @endif
        @if ($effect->outcome)
            <p class="mt-1.5 text-[14px] leading-normal text-ink-muted">{{ $effect->outcome }}</p>
        @endif
        <x-supplements::source-badges :keys="$effect->sourceKeys" :sources="$sources" />
    </div>
@else
    <div {{ $attributes->class('flex min-h-14 items-center gap-3 border-b border-line-soft py-2') }} data-effect>
        <x-ui.icon-tile :tone="$tones[$effect->category?->value] ?? 'stone'" :size="32">
            <x-dynamic-component :component="$icon" />
        </x-ui.icon-tile>
        <span class="min-w-0 flex-1 text-[15px] font-medium text-ink">{{ $title }}</span>
        <x-ui.grade :grade="$effect->evidenceGrade->value" />
    </div>
@endif
