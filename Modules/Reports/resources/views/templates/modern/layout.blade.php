<header class="hero">
    <p class="eyebrow">Supplement research</p>
    <h1>{{ $research->name }}</h1>
    <p class="hero-meta"><span class="pill">{{ $label }}</span> Generated {{ $generatedAt->format('F j, Y') }}</p>
</header>

<aside class="disclaimer">
    This is a research summary generated from published sources, not medical advice. Talk to a qualified clinician before starting a supplement.
</aside>

@foreach (['overview', 'effects', 'mechanisms', 'studies', 'sources'] as $section)
    @includeFirst(["reports::templates.{$template}.sections.{$section}", "reports::sections.{$section}"])
@endforeach
