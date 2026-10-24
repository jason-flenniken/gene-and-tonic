<?php

use Carbon\CarbonImmutable;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Services\RuleBasedGeneticRelevanceReader;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Enums\GeneRelation;
use Modules\Supplements\Enums\ResearchStatus;
use Modules\Supplements\Testing\FakeSupplementResearchReader;

const RELEVANCE_USER = 7;
const RELEVANCE_SUPPLEMENT = 11;

beforeEach(function () {
    $this->text = FakeTextGenerator::install();
    $this->profiles = FakeGeneticProfileReader::install();
    $this->research = FakeSupplementResearchReader::install();
});

afterEach(function () {
    expect($this->text->requests())->toBe([]);
});

function relevanceVariant(string $rsid, ?string $gene, string $genotype, ?string $ref = 'G', ?string $risk = 'A'): VariantData
{
    return new VariantData(
        rsid: $rsid,
        gene: $gene,
        genotype: $genotype,
        referenceAllele: $ref,
        riskAllele: $risk,
        category: null,
        affects: null,
        genotypeEffect: null,
        status: LookupStatus::Seeded,
        source: 'manual',
    );
}

/**
 * @param  list<GeneInteractionData>  $interactions
 */
function relevanceResearch(array $interactions, bool $geneticsChecked = true): SupplementResearchData
{
    return new SupplementResearchData(
        supplementId: RELEVANCE_SUPPLEMENT,
        name: 'Methylfolate',
        status: ResearchStatus::Complete,
        summary: null,
        safetyNotes: null,
        effects: [],
        mechanisms: [],
        studies: [],
        sources: [],
        completedAt: CarbonImmutable::now(),
        geneInteractions: $interactions,
        geneticsChecked: $geneticsChecked,
    );
}

function relevanceInteraction(string $gene, ?string $rsid, ?string $allele, GeneRelation $relation = GeneRelation::Helps): GeneInteractionData
{
    return new GeneInteractionData($gene, $rsid, $allele, $relation, "$gene explanation", ['S1']);
}

/**
 * @param  list<VariantData>  $variants
 * @param  list<GeneInteractionData>  $interactions
 */
function relevanceFor(array $variants, array $interactions, bool $geneticsChecked = true): GeneticRelevanceData
{
    test()->profiles->for(RELEVANCE_USER, new GeneticProfileData(RELEVANCE_USER, $variants));
    test()->research->with(relevanceResearch($interactions, $geneticsChecked));

    return app(GeneticRelevanceReader::class)->forUserAndSupplement(RELEVANCE_USER, RELEVANCE_SUPPLEMENT);
}

it('is bound to the rule-based reader', function () {
    expect(app(GeneticRelevanceReader::class))->toBeInstanceOf(RuleBasedGeneticRelevanceReader::class);
});

it('matches a gene-strand allele against a plus-strand heterozygote with one copy', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AG')],
        [relevanceInteraction('MTHFR', 'rs1801133', 'T')],
    );

    expect($relevance->supplementId)->toBe(RELEVANCE_SUPPLEMENT)
        ->and($relevance->hasProfile)->toBeTrue()
        ->and($relevance->geneticsChecked)->toBeTrue()
        ->and($relevance->hasMatches())->toBeTrue()
        ->and($relevance->matches)->toEqual([
            new GeneticMatchData(
                gene: 'MTHFR',
                rsid: 'rs1801133',
                genotype: 'AG',
                copies: 1,
                relation: GeneRelation::Helps,
                explanation: 'MTHFR explanation',
                sourceKeys: ['S1'],
                matchedOn: 'allele',
            ),
        ]);
});

it('counts two copies for a homozygous risk genotype', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AA')],
        [relevanceInteraction('MTHFR', 'rs1801133', 'A')],
    );

    expect($relevance->matches)->toHaveCount(1)
        ->and($relevance->matches[0]->copies)->toBe(2)
        ->and($relevance->matches[0]->matchedOn)->toBe('allele');
});

it('does not match when the user carries no copy of the allele', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'GG')],
        [relevanceInteraction('MTHFR', 'rs1801133', 'A')],
    );

    expect($relevance->matches)->toBe([])
        ->and($relevance->hasMatches())->toBeFalse()
        ->and($relevance->hasProfile)->toBeTrue();
});

it('matches an rsID-only interaction with copies null', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1805087', 'MTR', 'AG')],
        [relevanceInteraction('MTR', 'rs1805087', null, GeneRelation::Caution)],
    );

    expect($relevance->matches)->toHaveCount(1)
        ->and($relevance->matches[0]->rsid)->toBe('rs1805087')
        ->and($relevance->matches[0]->copies)->toBeNull()
        ->and($relevance->matches[0]->relation)->toBe(GeneRelation::Caution)
        ->and($relevance->matches[0]->matchedOn)->toBe('rsid');
});

it('treats an empty or unusable allele as an rsID-only match', function (string $allele) {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AG')],
        [relevanceInteraction('MTHFR', 'rs1801133', $allele)],
    );

    expect($relevance->matches)->toHaveCount(1)
        ->and($relevance->matches[0]->copies)->toBeNull()
        ->and($relevance->matches[0]->matchedOn)->toBe('rsid');
})->with(['empty' => '', 'blank' => '  ', 'two letters' => 'TT', 'HGVS-style' => 'C677T', 'outside the alphabet' => 'N']);

it('accepts a lower-case allele', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AG')],
        [relevanceInteraction('MTHFR', 'rs1801133', 't')],
    );

    expect($relevance->matches[0]->copies)->toBe(1)
        ->and($relevance->matches[0]->matchedOn)->toBe('allele');
});

it('matches a gene-only interaction against every variant of that gene, case-insensitively', function () {
    $relevance = relevanceFor(
        [
            relevanceVariant('rs601338', 'FUT2', 'AG'),
            relevanceVariant('rs1047781', 'fut2', 'AT', 'A', 'T'),
            relevanceVariant('rs1801133', 'MTHFR', 'AG'),
            relevanceVariant('rs9999999', null, 'CC'),
        ],
        [relevanceInteraction('FUT2', null, null)],
    );

    expect(array_map(fn (GeneticMatchData $match) => [$match->rsid, $match->copies, $match->matchedOn], $relevance->matches))
        ->toBe([['rs601338', null, 'gene'], ['rs1047781', null, 'gene']]);
});

it('does not fall back to a gene match when the user lacks the interaction rsID', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G')],
        [relevanceInteraction('MTHFR', 'rs1801133', 'T')],
    );

    expect($relevance->matches)->toBe([]);
});

it('keeps interaction order and reports a variant once per interaction', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AG'), relevanceVariant('rs1801133', 'MTHFR', 'AG')],
        [
            relevanceInteraction('MTHFR', 'rs1801133', 'T'),
            relevanceInteraction('MTHFR', null, null, GeneRelation::Related),
        ],
    );

    expect(array_map(fn (GeneticMatchData $match) => [$match->rsid, $match->matchedOn], $relevance->matches))
        ->toBe([['rs1801133', 'allele'], ['rs1801133', 'gene']]);
});

it('reports a user with no SNPs as having no profile', function () {
    $relevance = relevanceFor([], [relevanceInteraction('MTHFR', 'rs1801133', 'T')]);

    expect($relevance->hasProfile)->toBeFalse()
        ->and($relevance->geneticsChecked)->toBeTrue()
        ->and($relevance->matches)->toBe([]);
});

it('reports research that predates gene interactions as not checked', function () {
    $relevance = relevanceFor([relevanceVariant('rs1801133', 'MTHFR', 'AG')], [], geneticsChecked: false);

    expect($relevance->hasProfile)->toBeTrue()
        ->and($relevance->geneticsChecked)->toBeFalse()
        ->and($relevance->matches)->toBe([]);
});

it('reports a supplement without research as not checked', function () {
    $this->profiles->for(RELEVANCE_USER, new GeneticProfileData(RELEVANCE_USER, [relevanceVariant('rs1801133', 'MTHFR', 'AG')]));

    $relevance = app(GeneticRelevanceReader::class)->forUserAndSupplement(RELEVANCE_USER, 404);

    expect($relevance->supplementId)->toBe(404)
        ->and($relevance->geneticsChecked)->toBeFalse()
        ->and($relevance->matches)->toBe([]);
});

it('only reads the requested user\'s profile', function () {
    $this->profiles->for(99, new GeneticProfileData(99, [relevanceVariant('rs1801133', 'MTHFR', 'AA')]));

    $relevance = relevanceFor([relevanceVariant('rs1801133', 'MTHFR', 'GG')], [relevanceInteraction('MTHFR', 'rs1801133', 'A')]);

    expect($relevance->matches)->toBe([]);
});
