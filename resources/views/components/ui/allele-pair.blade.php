@props(['alleles' => [], 'effect' => null, 'size' => 30])

@php
    $letters = array_values(array_map(fn ($allele) => (string) $allele, array_slice($alleles, 0, 2)));
    $isEffect = fn (string $allele): bool => $effect !== null && $effect !== '' && strcasecmp($allele, $effect) === 0;
    $copies = count(array_filter($letters, $isEffect));
    $genotype = implode(' ', $letters);
    $label = 'Genotype '.$genotype;

    if ($effect !== null && $effect !== '') {
        $label .= match ($copies) {
            0 => '; no copies of effect allele '.$effect,
            1 => '; one copy of effect allele '.$effect,
            default => '; two copies of effect allele '.$effect,
        };
    }

    $dimension = 'width: '.(int) $size.'px; height: '.(int) $size.'px; font-size: '.max(11, (int) round($size * 0.43)).'px';
@endphp

<span {{ $attributes->class('inline-flex items-center gap-1') }} role="img" aria-label="{{ $label }}">
    @foreach ($letters as $allele)
        <span
            data-allele
            @if ($isEffect($allele)) data-effect @endif
            style="{{ $dimension }}"
            @class([
                'box-border inline-flex shrink-0 items-center justify-center rounded-full font-bold',
                'bg-juniper text-white' => $isEffect($allele),
                'border-[1.5px] border-[#D5DBD7] text-ink' => ! $isEffect($allele),
            ])
            aria-hidden="true"
        >{{ $allele }}</span>
    @endforeach
</span>
