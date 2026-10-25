<?php

use Carbon\CarbonImmutable;
use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Testing\FakeGeneticProfileReader;
use Modules\Intelligence\Testing\FakeTextGenerator;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Recommendations\Services\RuleBasedGeneticRelevanceReader;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\SupplementResearchData;
use Modules\Supplements\Data\SupplementSummaryData;
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

function relevanceVariant(string $rsid, ?string $gene, string $genotype, ?string $ref = 'G', ?string $risk = 'A', ?string $variantName = null): VariantData
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
        variantName: $variantName,
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
                allele: 'A',
            ),
        ]);
});

it('carries the plus-strand effect allele and the variant name on an allele match', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G', 'A1298C')],
        [relevanceInteraction('MTHFR', 'rs1801131', 'G')],
    );

    expect($relevance->matches)->toHaveCount(1)
        ->and($relevance->matches[0]->allele)->toBe('G')
        ->and($relevance->matches[0]->copies)->toBe(1)
        ->and($relevance->matches[0]->variantName)->toBe('A1298C');
});

it('leaves the allele null on rsID-only and gene-only matches', function () {
    $relevance = relevanceFor(
        [relevanceVariant('rs1801133', 'MTHFR', 'AG', variantName: 'C677T'), relevanceVariant('rs601338', 'FUT2', 'AG')],
        [relevanceInteraction('MTHFR', 'rs1801133', 'C677T'), relevanceInteraction('FUT2', null, null)],
    );

    expect(array_map(fn (GeneticMatchData $match) => [$match->rsid, $match->matchedOn, $match->allele, $match->variantName], $relevance->matches))
        ->toBe([['rs1801133', 'rsid', null, 'C677T'], ['rs601338', 'gene', null, null]]);
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

/**
 * @param  list<GeneInteractionData>  $interactions
 */
function relevanceSummary(int $supplementId, string $name, array $interactions, ResearchStatus $status = ResearchStatus::Complete): SupplementSummaryData
{
    return new SupplementSummaryData($supplementId, $name, $status, null, 0, 0, 0, [], $interactions);
}

/**
 * @return list<VariantData>
 */
function rankingVariants(): array
{
    return [
        relevanceVariant('rs1801133', 'MTHFR', 'AG'),
        relevanceVariant('rs1801131', 'MTHFR', 'GT', 'T', 'G'),
        relevanceVariant('rs1805087', 'MTR', 'AG'),
    ];
}

/**
 * @param  list<SupplementMatchesData>  $top
 * @return list<array{int, string, int}>
 */
function rankingRows(array $top): array
{
    return array_map(fn (SupplementMatchesData $supplement) => [$supplement->supplementId, $supplement->name, count($supplement->matches)], $top);
}

it('ranks supplements by match count and leaves out those with no matches', function () {
    $this->profiles->for(RELEVANCE_USER, new GeneticProfileData(RELEVANCE_USER, rankingVariants()));
    $this->research
        ->withSummary(relevanceSummary(1, 'B12', [relevanceInteraction('MTHFR', 'rs1801133', 'T'), relevanceInteraction('MTR', 'rs1805087', null)]))
        ->withSummary(relevanceSummary(2, 'Methylfolate', [relevanceInteraction('MTHFR', null, null), relevanceInteraction('MTR', 'rs1805087', null)], ResearchStatus::Searching))
        ->withSummary(relevanceSummary(3, 'Zinc', [relevanceInteraction('ZIP4', null, null)]));

    $top = app(GeneticRelevanceReader::class)->topForUser(RELEVANCE_USER, 5);

    expect(rankingRows($top))->toBe([[2, 'Methylfolate', 3], [1, 'B12', 2]])
        ->and($top[0]->status)->toBe(ResearchStatus::Searching)
        ->and($top[1]->matches[0])->toBeInstanceOf(GeneticMatchData::class)
        ->and($top[1]->matches[0]->allele)->toBe('A');
});

it('breaks a tie in match count by name and returns one supplement by default', function () {
    $this->profiles->for(RELEVANCE_USER, new GeneticProfileData(RELEVANCE_USER, rankingVariants()));
    $this->research
        ->withSummary(relevanceSummary(1, 'Riboflavin', [relevanceInteraction('MTR', 'rs1805087', null)]))
        ->withSummary(relevanceSummary(2, 'B12', [relevanceInteraction('MTR', 'rs1805087', null)]))
        ->withSummary(relevanceSummary(3, 'methylfolate', [relevanceInteraction('MTR', 'rs1805087', null)]));

    $reader = app(GeneticRelevanceReader::class);

    expect(rankingRows($reader->topForUser(RELEVANCE_USER, 2)))->toBe([[2, 'B12', 1], [3, 'methylfolate', 1]])
        ->and(rankingRows($reader->topForUser(RELEVANCE_USER)))->toBe([[2, 'B12', 1]]);
});

it('returns no top supplements for a user with no profile', function () {
    $this->profiles->for(99, new GeneticProfileData(99, rankingVariants()));
    $this->research->withSummary(relevanceSummary(1, 'B12', [relevanceInteraction('MTR', 'rs1805087', null)]));

    expect(app(GeneticRelevanceReader::class)->topForUser(RELEVANCE_USER, 5))->toBe([]);
});

it('reads the profile and the research listing once per request however often it is asked', function () {
    $reads = 0;
    $profiles = new class($reads) extends FakeGeneticProfileReader
    {
        public function __construct(public int &$reads) {}

        public function forUser(int $userId): GeneticProfileData
        {
            $this->reads++;

            return new GeneticProfileData($userId, [relevanceVariant('rs1801133', 'MTHFR', 'AG')]);
        }
    };
    app()->instance(GeneticProfileReader::class, $profiles);
    $this->research->with(relevanceResearch([new GeneInteractionData('MTHFR', 'rs1801133', 'A', GeneRelation::Helps, 'x', ['S1'])]));

    $reader = app(GeneticRelevanceReader::class);
    $reader->forUserAndSupplement(RELEVANCE_USER, RELEVANCE_SUPPLEMENT);
    $reader->forUserAndSupplement(RELEVANCE_USER, RELEVANCE_SUPPLEMENT);
    $reader->topForUser(RELEVANCE_USER, 5);

    expect($reads)->toBe(1)->and(app(GeneticRelevanceReader::class))->toBe($reader);
});
