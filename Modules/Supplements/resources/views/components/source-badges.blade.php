@props(['keys' => [], 'sources' => []])

{{-- $sources is keyed by source key; unknown keys are ignored. --}}
@php($known = collect($keys)->filter(fn ($key) => isset($sources[$key])))

@if ($known->isNotEmpty())
    <div class="mt-2 flex flex-wrap gap-1.5" data-source-badges>
        @foreach ($known as $key)
            @php($url = $sources[$key]->safeUrl())
            @php($badge = 'inline-flex items-center rounded-lg border border-line-input bg-surface px-2 py-0.5 text-[12px] font-semibold text-ink-muted')
            @if ($url)
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="{{ $sources[$key]->title }}" class="{{ $badge }} hover:bg-surface-muted">{{ $key }}</a>
            @else
                <span title="{{ $sources[$key]->title }}" class="{{ $badge }}">{{ $key }}</span>
            @endif
        @endforeach
    </div>
@endif
