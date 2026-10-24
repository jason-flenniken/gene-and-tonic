<header class="title-block">
    <p class="eyebrow">Supplement research report</p>
    <h1>{{ $research->name }}</h1>
    <dl class="meta">
        <div>
            <dt>Generated</dt>
            <dd>{{ $generatedAt->format('F j, Y') }}</dd>
        </div>
        <div>
            <dt>Template</dt>
            <dd>{{ $label }}</dd>
        </div>
    </dl>
</header>

<aside class="disclaimer">
    This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.
</aside>

@foreach (['overview', 'effects', 'mechanisms', 'studies', 'sources'] as $section)
    @includeFirst(["reports::templates.{$template}.sections.{$section}", "reports::sections.{$section}"])
@endforeach
