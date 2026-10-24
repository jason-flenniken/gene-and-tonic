<section class="report-section" id="studies">
    <h2>Studies</h2>

    @if ($research->studies === [])
        <p class="empty">No studies recorded.</p>
    @else
        <table class="studies">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Design</th>
                    <th>Population</th>
                    <th>n</th>
                    <th>Year</th>
                    <th>Finding</th>
                    <th>Grade</th>
                    <th>PubMed</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($research->studies as $study)
                    <tr>
                        <td class="study-title">{{ $study->title }} @include('reports::partials.citations', ['keys' => $study->sourceKey === null ? [] : [$study->sourceKey]])</td>
                        <td>{{ $study->design }}</td>
                        <td>{{ $study->population }}</td>
                        <td>{{ $study->sampleSize }}</td>
                        <td>{{ $study->year }}</td>
                        <td>{{ $study->finding }}</td>
                        <td>{{ $study->evidenceGrade ? ucfirst($study->evidenceGrade->value) : '' }}</td>
                        <td>
                            @if ($study->pubmedUrl())
                                <a href="{{ $study->pubmedUrl() }}">PMID {{ $study->pmid }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
