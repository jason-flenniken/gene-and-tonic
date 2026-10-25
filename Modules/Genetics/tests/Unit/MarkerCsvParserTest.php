<?php

use Modules\Genetics\Support\Parsing\FormatDetector;
use Modules\Genetics\Support\Parsing\MarkerCsvParser;
use Modules\Genetics\Support\Parsing\ParsedVariant;

function markerCsvFile(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'genetics-marker-csv-');
    file_put_contents($path, $contents);

    return $path;
}

/** @return list<array{0: string, 1: string, 2: string|null}> */
function markerCsvRows(string $contents): array
{
    $path = markerCsvFile($contents);
    $parser = (new FormatDetector)->detect($path, 'csv');

    expect($parser)->toBeInstanceOf(MarkerCsvParser::class);

    return array_map(
        fn (ParsedVariant $variant): array => [$variant->rsid, $variant->genotype, $variant->gene],
        iterator_to_array($parser->parse($path), false),
    );
}

it('prefers an exact rsID column over an earlier marker column', function (string $header, string $row) {
    expect(markerCsvRows($header."\n".$row."\n"))->toBe([['rs1801133', 'AG', 'MTHFR']]);
})->with([
    'marker first' => ['Marker,rsID,Gene,Genotype', 'MTHFR C677T,rs1801133,MTHFR,A/G'],
    'variant first' => ['Variant,Gene,Genotype,SNP', 'C677T,MTHFR,A/G,rs1801133'],
]);

it('matches header names split on slashes and similar separators', function (string $header) {
    expect(markerCsvRows($header."\nrs1801133,MTHFR,A/G\n"))->toBe([['rs1801133', 'AG', 'MTHFR']]);
})->with([
    'rsID,Gene / Locus,Genotype / Result',
    'RSID (dbSNP),Gene|Symbol,Result; call',
    '"Marker, rsID",Gene,Alleles',
]);

it('does not split header names on spaces', function () {
    $path = markerCsvFile("Marker Type,Gene,Genotype\nSNP,MTHFR,A/G\n");

    expect((new FormatDetector)->detect($path, 'csv'))->toBeNull();
});

it('takes the first allele pair from a messy genotype cell', function (string $cell, ?string $expected) {
    $rows = markerCsvRows("rsID,Genotype\nrs1801133,\"{$cell}\"\n");

    expect($rows)->toBe($expected === null ? [] : [['rs1801133', $expected, null]]);
})->with([
    'clean' => ['A/G', 'AG'],
    'lowercase clean' => ['c/c', 'CC'],
    'genomic then protein' => ['GRCh38 genomic A/G; protein Asp919Gly', 'AG'],
    'trailing note' => ['A/G (as reported)', 'AG'],
    'protein and star alleles after' => ['A/G; Ala503/Val503; *1/*28', 'AG'],
    'two pairs, first wins' => ['A/G genomic (C/T transcript)', 'AG'],
    'two pairs, other order' => ['C/T genomic (A/G transcript)', 'CT'],
    'bare pair' => ['TC (heterozygous)', 'CT'],
    'protein change only' => ['protein Arg/Cys', null],
    'lowercase prose' => ['as reported at a lab', null],
    'star alleles' => ['*2/*2', null],
    'absent' => ['absent', null],
]);

it('splits rows into read, unread and no-call when scanning', function () {
    $path = markerCsvFile(implode("\n", [
        'Category,Marker,rsID,Gene / Locus,Genotype / Result,Interpretation / Notes',
        'METHYLATION,MTHFR C677T,rs1801133,MTHFR,A/G,SECRET-NOTE-1',
        'DETOX,CYP2C19*2,rs4244285,CYP2C19,*2/*2,SECRET-NOTE-2',
        'DETOX,No call,rs90000001,ABC1,--,SECRET-NOTE-3',
        'DETOX,No call,rs90000002,ABC1,0/0,SECRET-NOTE-3',
        'LIPIDS,APOE,,APOE,E3/E4,SECRET-NOTE-4 rs-free',
        'B12,Only in notes,,FUT2,A/G,see rs601338',
    ])."\n");

    $scan = (new MarkerCsvParser)->scan($path, 100);

    expect($scan)->not->toBeNull()
        ->and($scan->rows)->toBe(6)
        ->and(array_map(fn (ParsedVariant $v): string => $v->rsid, $scan->read()))->toBe(['rs1801133'])
        ->and($scan->readRatio())->toBe(1 / 3)
        ->and($scan->leftoverLines())->toBe([
            'Marker: CYP2C19*2 | rsID: rs4244285 | Gene / Locus: CYP2C19 | Genotype / Result: *2/*2',
            'Marker: Only in notes | Gene / Locus: FUT2 | Genotype / Result: A/G',
        ])
        ->and(implode("\n", $scan->candidateLines()))->not->toContain('SECRET-NOTE')->not->toContain('METHYLATION');
});

it('ignores an unread row whose cells do not line up with the header', function () {
    $path = markerCsvFile("rsID,Genotype,Notes\nrs4680,A/A,ok\nrs4244285,*2/*2,\"first line of a note\nrs1801133 second line\"\n");

    $scan = (new MarkerCsvParser)->scan($path, 100);

    expect($scan->candidates)->toHaveCount(2)
        ->and($scan->leftoverLines())->toBe(['rsID: rs4244285 | Genotype: *2/*2'])
        ->and($scan->readRatio())->toBe(0.5);
});

it('returns null when the file has more marker rows than the limit', function () {
    $path = markerCsvFile("rsid,genotype\nrs1,AG\nrs2,*2/*2\nrs3,AG\nrs4,--\n");

    expect((new MarkerCsvParser)->scan($path, 2))->toBeNull()
        ->and((new MarkerCsvParser)->scan($path, 3))->not->toBeNull();
});
