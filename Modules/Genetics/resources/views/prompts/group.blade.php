Items (id | gene | category):
@foreach ($items as $item)
{{ $item['id'] }} | {{ $item['gene'] }} | {{ $item['category'] }}
@endforeach
