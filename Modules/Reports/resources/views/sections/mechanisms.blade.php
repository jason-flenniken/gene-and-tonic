<section class="report-section" id="mechanisms">
    <h2>Mechanisms</h2>

    @forelse ($research->mechanisms as $mechanism)
        <article class="item">
            <p>{{ $mechanism->description }} @include('reports::partials.citations', ['keys' => $mechanism->sourceKeys])</p>
        </article>
    @empty
        <p class="empty">No mechanisms recorded.</p>
    @endforelse
</section>
