<?php

use Modules\Genetics\Data\Genotype;

it('normalizes separators, case and allele order', function (string $raw, string $expected) {
    expect(Genotype::normalize($raw))->toBe($expected);
})->with([
    'slash, lower case' => ['c/t', 'CT'],
    'unsorted' => ['TC', 'CT'],
    'semicolon' => ['C;T', 'CT'],
]);

it('keeps single-allele calls and insertion/deletion calls', function (string $raw, string $expected) {
    expect(Genotype::normalize($raw))->toBe($expected);
})->with([
    'hemizygous' => ['A', 'A'],
    'pipe and spaces' => [' g | a ', 'AG'],
    'indel' => ['I/D', 'DI'],
]);

it('returns null for no-calls', function (string $raw) {
    expect(Genotype::normalize($raw))->toBeNull();
})->with(['--', '00', '', '0', ' - / - ']);

it('rejects genotypes outside the allele alphabet or longer than two alleles', function (string $raw) {
    Genotype::normalize($raw);
})->with(['XY', 'AGT', 'A-', 'N'])->throws(InvalidArgumentException::class, 'Invalid genotype.');

it('complements A/T and C/G and leaves D and I alone', function () {
    expect(Genotype::complement('ACGT'))->toBe('TGCA')
        ->and(Genotype::complement('DI'))->toBe('DI');
});

it('resolves genotypes to the plus strand', function (string $genotype, ?string $ref, ?string $risk, string $expected) {
    expect(Genotype::toPlusStrand($genotype, $ref, $risk))->toBe($expected);
})->with([
    'already plus' => ['AA', 'G', 'A', 'AA'],
    'gene strand, complemented' => ['TT', 'G', 'A', 'AA'],
    'MTHFR C677T heterozygote from the gene strand' => ['CT', 'G', 'A', 'AG'],
    'strand-ambiguous A/T left as given' => ['AT', 'A', 'T', 'AT'],
    'strand-ambiguous C/G left as given' => ['CC', 'C', 'G', 'CC'],
    'unknown reference alleles' => ['TT', null, null, 'TT'],
    'neither strand matches' => ['AC', 'G', 'A', 'AC'],
]);

it('counts copies of an allele across strands', function (string $genotype, string $allele, ?string $ref, ?string $risk, int $expected) {
    expect(Genotype::copiesOf($genotype, $allele, $ref, $risk))->toBe($expected);
})->with([
    'gene-strand T maps to plus-strand A' => ['AG', 'T', 'G', 'A', 1],
    'homozygous risk' => ['AA', 'A', 'G', 'A', 2],
    'none' => ['GG', 'A', 'G', 'A', 0],
    'genotype stored before strand was known' => ['TT', 'A', 'G', 'A', 2],
    'strand-ambiguous pair counts as given' => ['AT', 'T', 'A', 'T', 1],
    'hemizygous' => ['A', 'A', 'G', 'A', 1],
]);

it('resolves an effect allele to the plus strand', function (string $allele, ?string $ref, ?string $risk, ?string $expected) {
    expect(Genotype::effectAlleleOnPlusStrand($allele, $ref, $risk))->toBe($expected);
})->with([
    'gene-strand T maps to plus-strand A' => ['T', 'G', 'A', 'A'],
    'already plus' => ['A', 'G', 'A', 'A'],
    'lower case with spaces' => [' t ', 'G', 'A', 'A'],
    'unknown reference alleles' => ['T', null, null, 'T'],
    'strand-ambiguous pair left as given' => ['T', 'A', 'T', 'T'],
    'fits neither strand' => ['A', 'G', null, 'A'],
    'deletion' => ['D', 'I', 'D', 'D'],
]);

it('returns null for an effect allele that is not one allele', function (string $allele) {
    expect(Genotype::effectAlleleOnPlusStrand($allele, 'G', 'A'))->toBeNull();
})->with(['empty' => '', 'blank' => '  ', 'two letters' => 'TT', 'HGVS-style' => 'C677T', 'outside the alphabet' => 'N']);
