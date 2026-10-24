<section class="report-section" id="sources">
    <h2>Sources</h2>

    @if ($research->sources === [])
        <p class="empty">No sources recorded.</p>
    @else
        <ol class="sources">
            @foreach ($research->sources as $source)
                <li>
                    <span class="source-number">[{{ $loop->iteration }}]</span>
                    @if ($source->safeUrl())
                        <a href="{{ $source->safeUrl() }}" class="source-title">{{ $source->title }}</a>
                    @else
                        <span class="source-title">{{ $source->title }}</span>
                    @endif
                    @if ($source->domain)
                        <span class="source-domain">{{ $source->domain }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
