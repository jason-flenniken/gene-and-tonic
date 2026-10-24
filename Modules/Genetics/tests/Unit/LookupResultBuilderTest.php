<?php

use Modules\Genetics\Support\LookupResultBuilder;
use Modules\Intelligence\Data\SourceDocument;

function lookupBuilderOutput(): array
{
    return [
        'gene' => 'MTHFR', 'category' => 'Methylation', 'affects' => 'Folate handling',
        'reference_allele' => 'G', 'risk_allele' => 'A', 'genotype_effects' => [],
        'source_ids' => ['S1', 'S2', 'S3'],
    ];
}

it('stores only sources with a safe url', function () {
    $result = LookupResultBuilder::build(lookupBuilderOutput(), [
        new SourceDocument('S1', 'https://user:pw@x.com/a', 'Bad', 'x.com', null, 'md'),
        new SourceDocument('S2', 'https://exämple.com/a', 'Bad host', 'exämple.com', null, 'md'),
        new SourceDocument('S3', 'https://www.snpedia.com/index.php/Rs1', 'Good', 'www.snpedia.com', null, 'md'),
    ]);

    expect(array_column($result->sources, 'url'))->toBe(['https://www.snpedia.com/index.php/Rs1']);
});
