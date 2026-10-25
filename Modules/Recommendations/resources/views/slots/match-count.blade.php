@auth
    @php($relevance = app(\Modules\Recommendations\Contracts\GeneticRelevanceReader::class)->forUserAndSupplement((int) auth()->id(), $supplementId))
    @if ($relevance->hasProfile)
        <span data-match-count>· {{ $relevance->hasMatches() ? count($relevance->matches).' '.\Illuminate\Support\Str::plural('gene match', count($relevance->matches)) : 'no gene matches' }}</span>
    @endif
@endauth
