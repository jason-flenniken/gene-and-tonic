<?php

use Modules\Genetics\Support\Parsing\AncestryParser;
use Modules\Genetics\Support\Parsing\FormatDetector;
use Modules\Genetics\Support\Parsing\MarkerCsvParser;
use Modules\Genetics\Support\Parsing\ParsedKind;
use Modules\Genetics\Support\Parsing\ParsedVariant;
use Modules\Genetics\Support\Parsing\RowParser;
use Modules\Genetics\Support\Parsing\TwentyThreeAndMeParser;
use Modules\Genetics\Support\Parsing\VendorCsvParser;

function geneticsFixture(string $name): string
{
    return __DIR__.'/../Fixtures/'.$name;
}

/** @return list<array{0: string, 1: string, 2: string|null}> */
function geneticsParsedRows(RowParser $parser, string $path): array
{
    $rows = [];

    foreach ($parser->parse($path) as $variant) {
        expect($variant)->toBeInstanceOf(ParsedVariant::class);
        $rows[] = [$variant->rsid, $variant->genotype, $variant->gene];
    }

    return $rows;
}

function geneticsTempFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'genetics-test-');
    file_put_contents($path, $contents);

    return $path;
}

it('detects and parses a 23andMe export, skipping no-calls and internal ids', function () {
    $parser = (new FormatDetector)->detect(geneticsFixture('23andme.txt'), 'txt');

    expect($parser)->toBeInstanceOf(TwentyThreeAndMeParser::class)
        ->and($parser->kind())->toBe(ParsedKind::RawExport)
        ->and(geneticsParsedRows($parser, geneticsFixture('23andme.txt')))->toBe([
            ['rs1801133', 'AG', null],
            ['rs1801131', 'GT', null],
            ['rs4680', 'AA', null],
            ['rs90000001', 'AA', null],
            ['rs90000002', 'CT', null],
            ['rs90000003', 'GG', null],
            ['rs90000004', 'AC', null],
            ['rs90000005', 'A', null],
        ]);
});

it('detects and parses an AncestryDNA export with split alleles and chromosome 23', function () {
    $parser = (new FormatDetector)->detect(geneticsFixture('ancestry.txt'), 'txt');

    expect($parser)->toBeInstanceOf(AncestryParser::class)
        ->and($parser->kind())->toBe(ParsedKind::RawExport)
        ->and(geneticsParsedRows($parser, geneticsFixture('ancestry.txt')))->toBe([
            ['rs1801133', 'AG', null],
            ['rs601338', 'AA', null],
            ['rs90000011', 'TT', null],
            ['rs1805087', 'AG', null],
            ['rs90000013', 'CT', null],
        ]);
});

it('detects and parses a MyHeritage CSV with quoted values', function () {
    $parser = (new FormatDetector)->detect(geneticsFixture('myheritage.csv'), 'csv');

    expect($parser)->toBeInstanceOf(VendorCsvParser::class)
        ->and($parser->kind())->toBe(ParsedKind::RawExport)
        ->and(geneticsParsedRows($parser, geneticsFixture('myheritage.csv')))->toBe([
            ['rs1801133', 'GG', null],
            ['rs4680', 'AG', null],
            ['rs90000021', 'CT', null],
            ['rs90000023', 'AA', null],
        ]);
});

it('detects an FTDNA CSV without comment lines', function () {
    $path = geneticsTempFile("RSID,CHROMOSOME,POSITION,RESULT\r\n\"rs4680\",\"22\",\"19963748\",\"GA\"\r\n");

    $parser = (new FormatDetector)->detect($path, 'csv');

    expect($parser)->toBeInstanceOf(VendorCsvParser::class)
        ->and(geneticsParsedRows($parser, $path))->toBe([['rs4680', 'AG', null]]);
});

it('detects a genome CSV with chromosome and position columns as a raw export', function (string $header, array $expected) {
    $path = geneticsTempFile($header."\n".'rs1801133,1,11796321,AG,X'."\n");

    $parser = (new FormatDetector)->detect($path, 'csv');

    expect($parser)->toBeInstanceOf(VendorCsvParser::class)
        ->and($parser->kind())->toBe(ParsedKind::RawExport)
        ->and(geneticsParsedRows($parser, $path))->toBe($expected);
})->with([
    'lowercase' => ['rsid,chromosome,position,genotype', [['rs1801133', 'AG', null]]],
    'aliases and extra column' => ['SNP,Chromosome,Position,Result,Note', [['rs1801133', 'AG', null]]],
]);

it('detects a marker CSV by header aliases, keeping rows with a bad gene but no others that are invalid', function () {
    $parser = (new FormatDetector)->detect(geneticsFixture('markers.csv'), 'csv');

    expect($parser)->toBeInstanceOf(MarkerCsvParser::class)
        ->and($parser->kind())->toBe(ParsedKind::MarkerList)
        ->and(geneticsParsedRows($parser, geneticsFixture('markers.csv')))->toBe([
            ['rs1801133', 'CT', 'MTHFR'],
            ['rs90000031', 'AG', 'ABC1'],
            ['rs90000032', 'GG', 'ABC2'],
            ['rs90000033', 'TT', null],
            ['rs90000034', 'CC', 'XYZ9'],
            ['rs90000035', 'AT', null],
        ]);
});

it('accepts every marker CSV header alias in any case and column order', function (string $header) {
    $path = geneticsTempFile($header."\n".'rs1801133,AG,mthfr'."\n");

    $parser = (new FormatDetector)->detect($path, 'csv');

    expect($parser)->toBeInstanceOf(MarkerCsvParser::class)
        ->and(geneticsParsedRows($parser, $path))->toBe([['rs1801133', 'AG', 'MTHFR']]);
})->with([
    'rsid,genotype,gene',
    'SNP,Result,Symbol',
    'Marker,Call,Gene',
    'variant,ALLELES,gene',
]);

it('reads the marker CSV columns by header position', function () {
    $path = geneticsTempFile("Result,Gene,Variant\nT/C,MTHFR,rs1801133\n");

    expect(geneticsParsedRows((new FormatDetector)->detect($path, 'csv'), $path))->toBe([['rs1801133', 'CT', 'MTHFR']]);
});

it('returns null for unrecognised text', function () {
    expect((new FormatDetector)->detect(geneticsFixture('unrecognised.txt'), 'txt'))->toBeNull();
});

it('returns null for a pdf even when its text looks like a known format', function () {
    expect((new FormatDetector)->detect(geneticsFixture('23andme.txt'), 'pdf'))->toBeNull();
});

it('looks at no more than the first 50 lines', function () {
    $path = geneticsTempFile(str_repeat("# comment\n", 50)."rs1801133\t1\t11796321\tAG\n");

    expect((new FormatDetector)->detect($path, 'txt'))->toBeNull();
});

it('does not treat a tab-separated Ancestry header as a marker CSV', function () {
    $path = geneticsTempFile("rsid\tchromosome\tposition\tallele1\tallele2\nrs1\t1\t1\tA\tG\n");

    expect((new FormatDetector)->detect($path, 'txt'))->toBeInstanceOf(AncestryParser::class);
});

it('builds a parsed variant only from a valid rsID and genotype', function () {
    expect(ParsedVariant::fromRaw(' RS1801133 ', 'g/a', ' mthfr '))->toEqual(new ParsedVariant('rs1801133', 'AG', 'MTHFR'))
        ->and(ParsedVariant::fromRaw('rs1', 'AG', '<img>'))->toEqual(new ParsedVariant('rs1', 'AG', null))
        ->and(ParsedVariant::fromRaw('i7000', 'AG', null))->toBeNull()
        ->and(ParsedVariant::fromRaw('rs12345678901', 'AG', null))->toBeNull()
        ->and(ParsedVariant::fromRaw('rs1', '--', null))->toBeNull()
        ->and(ParsedVariant::fromRaw('rs1', 'ZZ', null))->toBeNull();
});
