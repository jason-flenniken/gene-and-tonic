@props(['active' => false])

<x-ui.segmented.item :active="$active" {{ $attributes }}>
    {{ $slot }}
</x-ui.segmented.item>
