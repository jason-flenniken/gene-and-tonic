{{-- Citation markers [n] for $keys; keys that are not in the source list are dropped. --}}
@php($numbers = collect($keys)->map(fn ($key) => $sourceNumbers[$key] ?? null)->filter()->unique())
@if ($numbers->isNotEmpty())<sup class="cite">{{ $numbers->map(fn ($number) => "[{$number}]")->implode(' ') }}</sup>@endif
