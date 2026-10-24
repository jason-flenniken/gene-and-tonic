Supplement: {{ $supplement }}

Sources:
@foreach ($sources as $s)
<source id="{{ $s->id }}" url="{{ $s->url }}" title="{{ $s->title }}">{{ $s->markdown }}</source>

@endforeach
