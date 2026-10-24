SNP: {{ $rsid }}
@if ($gene !== null)
Gene hint (unverified, may be wrong): {{ $gene }}
@endif

Sources:
@foreach ($sources as $s)
<source id="{{ $s->id }}" url="{{ $s->url }}" title="{{ $s->title }}">{{ $s->markdown }}</source>

@endforeach
