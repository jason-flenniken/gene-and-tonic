<section class="report-section" id="effects">
    <h2>Effects</h2>

    @forelse ($research->effects as $effect)
        <article class="item">
            <h3>{{ $effect->claim }} @include('reports::partials.citations', ['keys' => $effect->sourceKeys])</h3>
            <p class="grade">Evidence: {{ ucfirst($effect->evidenceGrade->value) }}</p>
            @if ($effect->outcome)
                <p>{{ $effect->outcome }}</p>
            @endif
        </article>
    @empty
        <p class="empty">No effects recorded.</p>
    @endforelse
</section>
