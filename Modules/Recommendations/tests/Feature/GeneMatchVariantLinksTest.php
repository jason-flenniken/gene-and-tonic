<?php

use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Recommendations\Services\GeneMatchVariantLinks;
use Modules\Shared\Contracts\VariantLinks;
use Modules\Shared\Data\LinkedSupplementData;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\SupplementSummaryData;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;

beforeEach(function () {
    $this->text = FakeTextGenerator::install();
    $this->profiles = FakeGeneticProfileReader::install();
    $this->research = FakeSupplementResearchReader::install();
});

afterEach(function () {
    expect($this->text->requests())->toBe([]);
});

function linksVariant(string $rsid, string $gene, string $genotype, string $ref, string $risk): VariantData
{
    return new VariantData($rsid, $gene, $genotype, $ref, $risk, null, null, null, LookupStatus::Seeded, 'manual');
}

function linksSupplement(int $supplementId, string $name, GeneInteractionData ...$interactions): SupplementSummaryData
{
    return new SupplementSummaryData($supplementId, $name, ResearchStatus::Complete, null, 0, 0, 0, [], array_values($interactions));
}

function linksInteraction(string $gene, ?string $rsid, ?string $allele): GeneInteractionData
{
    return new GeneInteractionData($gene, $rsid, $allele, GeneRelation::Helps, "$gene explanation", ['S1']);
}

it('is bound in place of the empty Shared default', function () {
    expect(app(VariantLinks::class))->toBeInstanceOf(GeneMatchVariantLinks::class);
});

it('links a matched rsID to the supplement it matches', function () {
    $this->profiles->for(7, new GeneticProfileData(7, [linksVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G')]));
    $this->research
        ->withSummary(linksSupplement(11, 'Methylfolate', linksInteraction('MTHFR', 'rs1801131', 'G')))
        ->withSummary(linksSupplement(12, 'Zinc', linksInteraction('ZIP4', null, null)));

    expect(app(VariantLinks::class)->forUser(7))->toEqual([
        'rs1801131' => [new LinkedSupplementData(11, 'Methylfolate')],
    ]);
});

it('lists a supplement once per rsID and every supplement the rsID matches', function () {
    $this->profiles->for(7, new GeneticProfileData(7, [
        linksVariant('rs1801133', 'MTHFR', 'AG', 'G', 'A'),
        linksVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G'),
    ]));
    $this->research
        ->withSummary(linksSupplement(11, 'Methylfolate', linksInteraction('MTHFR', 'rs1801133', 'T'), linksInteraction('MTHFR', null, null)))
        ->withSummary(linksSupplement(12, 'B12', linksInteraction('MTHFR', 'rs1801133', null)));

    $links = app(VariantLinks::class)->forUser(7);

    expect(array_map(fn (array $supplements) => array_map(fn (LinkedSupplementData $supplement) => $supplement->name, $supplements), $links))
        ->toBe([
            'rs1801133' => ['Methylfolate', 'B12'],
            'rs1801131' => ['Methylfolate'],
        ]);
});

it('links nothing for a user with no profile', function () {
    $this->research->withSummary(linksSupplement(11, 'Methylfolate', linksInteraction('MTHFR', null, null)));

    expect(app(VariantLinks::class)->forUser(7))->toBe([]);
});

it('never links rsIDs that only another user holds, and carries no genotypes', function () {
    $this->profiles
        ->for(7, new GeneticProfileData(7, [linksVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G')]))
        ->for(8, new GeneticProfileData(8, [linksVariant('rs1801133', 'MTHFR', 'AA', 'G', 'A')]));
    $this->research->withSummary(linksSupplement(11, 'Methylfolate', linksInteraction('MTHFR', null, null)));

    $links = app(VariantLinks::class)->forUser(7);
    $serialized = json_encode(array_map(fn (array $supplements) => array_map(fn (LinkedSupplementData $supplement) => $supplement->toArray(), $supplements), $links));

    expect(array_keys($links))->toBe(['rs1801131'])
        ->and($serialized)->not->toContain('genotype')
        ->and($serialized)->not->toContain('"GT"')
        ->and($serialized)->not->toContain('"AA"');
});
