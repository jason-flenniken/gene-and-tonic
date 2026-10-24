<main data-template="testing">
    <h1>{{ $research->name }}</h1>
    @foreach (['overview', 'effects', 'mechanisms', 'studies', 'sources'] as $section)
        @includeFirst(["reports::templates.{$template}.sections.{$section}", "reports::sections.{$section}"])
    @endforeach
</main>
