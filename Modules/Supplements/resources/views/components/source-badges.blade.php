@props(['keys' => [], 'sources' => []])

{{-- $sources is keyed by source key; unknown keys are ignored. --}}
@php($known = collect($keys)->filter(fn ($key) => isset($sources[$key])))

@if ($known->isNotEmpty())
    <div class="mt-2 flex flex-wrap gap-1" data-source-badges>
        @foreach ($known as $key)
            @php($url = $sources[$key]->safeUrl())
            @if ($url)
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $sources[$key]->title }}">
                    <flux:badge size="sm">{{ $key }}</flux:badge>
                </a>
            @else
                <flux:badge size="sm" title="{{ $sources[$key]->title }}">{{ $key }}</flux:badge>
            @endif
        @endforeach
    </div>
@endif
