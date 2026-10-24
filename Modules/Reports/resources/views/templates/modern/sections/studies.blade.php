<section class="report-section" id="studies">
    <h2>Studies</h2>

    @if ($research->studies === [])
        <p class="empty">No studies recorded.</p>
    @else
        <div class="study-cards">
            @foreach ($research->studies as $study)
                <article class="study-card">
                    <h3>{{ $study->title }} @include('reports::partials.citations', ['keys' => $study->sourceKey === null ? [] : [$study->sourceKey]])</h3>
                    <p class="study-tags">
                        @if ($study->design)<span class="pill">{{ $study->design }}</span>@endif
                        @if ($study->year)<span class="pill">{{ $study->year }}</span>@endif
                        @if ($study->sampleSize)<span class="pill">n = {{ $study->sampleSize }}</span>@endif
                        @if ($study->evidenceGrade)<span class="pill pill-accent">{{ ucfirst($study->evidenceGrade->value) }}</span>@endif
                    </p>
                    @if ($study->population)<p class="study-population">{{ $study->population }}</p>@endif
                    @if ($study->finding)<p class="study-finding">{{ $study->finding }}</p>@endif
                    @if ($study->pubmedUrl())
                        <p class="study-link"><a href="{{ $study->pubmedUrl() }}">PMID {{ $study->pmid }}</a></p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</section>
