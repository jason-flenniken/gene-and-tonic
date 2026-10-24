<section class="report-section" id="overview">
    <h2>Overview</h2>

    <h3>Summary</h3>
    <p class="prose">{{ $research->summary ?? 'No summary available.' }}</p>

    <h3>Safety notes</h3>
    <p class="prose">{{ $research->safetyNotes ?? 'No safety notes recorded.' }}</p>
</section>
