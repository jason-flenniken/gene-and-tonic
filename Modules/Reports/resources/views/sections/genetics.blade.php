@if ($relevance?->hasMatches())
<section class="report-section" id="genetics">
    <h2>Your genetic relevance</h2>

    @foreach ($relevance->matches as $match)
        <article class="item">
            <h3>{{ $match->gene }} {{ $match->rsid }} @include('reports::partials.citations', ['keys' => $match->sourceKeys])</h3>
            <p class="grade">Genotype: {{ $match->genotype }}@if ($match->copies !== null) ({{ $match->copies }} {{ $match->copies === 1 ? 'copy' : 'copies' }})@endif | {{ $match->relation->label() }}</p>
            <p>{{ $match->explanation }}</p>
        </article>
    @endforeach

    <p class="empty">Educational summary of published research, not medical advice or a diagnosis. Talk to a qualified clinician before acting on genetic results.</p>
</section>
@endif
