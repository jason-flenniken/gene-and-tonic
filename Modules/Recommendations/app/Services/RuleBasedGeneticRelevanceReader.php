<?php

namespace Modules\Recommendations\Services;

use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Recommendations\Data\SupplementMatchesData;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\GeneInteractionData;
use Modules\Supplements\Data\SupplementSummaryData;

final class RuleBasedGeneticRelevanceReader implements GeneticRelevanceReader
{
    /**
     * Bound as scoped: one instance per request. The profile is not cached here:
     * the Genetics reader memoizes it per request and flushes on writes, so
     * calling it directly is cheap and never stale. The research listing is read
     * once per request and does not see Supplements writes made in that request.
     *
     * @var array<int, SupplementSummaryData>|null
     */
    private ?array $summaryCache = null;

    public function __construct(
        private GeneticProfileReader $profiles,
        private SupplementResearchReader $research,
    ) {}

    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData
    {
        $profile = $this->profiles->forUser($userId);
        $summary = $this->summaries()[$supplementId] ?? null;

        if ($summary === null) {
            return new GeneticRelevanceData($supplementId, ! $profile->isEmpty(), false, []);
        }

        return new GeneticRelevanceData(
            supplementId: $supplementId,
            hasProfile: ! $profile->isEmpty(),
            geneticsChecked: $summary->geneticsChecked,
            matches: $this->matchesForAll($summary->geneInteractions, $profile->variants),
        );
    }

    /** @return array<int, SupplementSummaryData> */
    private function summaries(): array
    {
        if ($this->summaryCache === null) {
            $this->summaryCache = [];

            foreach ($this->research->all() as $summary) {
                $this->summaryCache[$summary->supplementId] = $summary;
            }
        }

        return $this->summaryCache;
    }

    public function topForUser(int $userId, int $limit = 1): array
    {
        $profile = $this->profiles->forUser($userId);

        if ($profile->isEmpty()) {
            return [];
        }

        $top = [];

        foreach ($this->summaries() as $supplement) {
            $matches = $this->matchesForAll($supplement->geneInteractions, $profile->variants);

            if ($matches !== []) {
                $top[] = new SupplementMatchesData($supplement->supplementId, $supplement->name, $supplement->status, $matches);
            }
        }

        usort($top, fn (SupplementMatchesData $a, SupplementMatchesData $b): int => count($b->matches) <=> count($a->matches) ?: strcasecmp($a->name, $b->name));

        return array_slice($top, 0, max(0, $limit));
    }

    /**
     * @param  list<GeneInteractionData>  $interactions
     * @param  list<VariantData>  $variants
     * @return list<GeneticMatchData>
     */
    private function matchesForAll(array $interactions, array $variants): array
    {
        $matches = [];

        foreach ($interactions as $interaction) {
            array_push($matches, ...$this->matchesFor($interaction, $variants));
        }

        return $matches;
    }

    /**
     * Applies the first rule that fits the interaction: rsID and allele, rsID
     * only, then gene only. At most one match per rsID.
     *
     * @param  list<VariantData>  $variants
     * @return list<GeneticMatchData>
     */
    private function matchesFor(GeneInteractionData $interaction, array $variants): array
    {
        $matches = [];

        foreach ($variants as $variant) {
            $match = $interaction->rsid === null
                ? $this->geneMatch($interaction, $variant)
                : $this->rsidMatch($interaction, $variant);

            if ($match !== null) {
                $matches[strtolower($variant->rsid)] ??= $match;
            }
        }

        return array_values($matches);
    }

    private function rsidMatch(GeneInteractionData $interaction, VariantData $variant): ?GeneticMatchData
    {
        if (strcasecmp((string) $interaction->rsid, $variant->rsid) !== 0) {
            return null;
        }

        $allele = Genotype::effectAlleleOnPlusStrand((string) $interaction->allele, $variant->referenceAllele, $variant->riskAllele);

        if ($allele === null) {
            return $this->match($interaction, $variant, null, GeneticMatchData::MATCHED_ON_RSID);
        }

        $copies = Genotype::copiesOf($variant->genotype, $allele, $variant->referenceAllele, $variant->riskAllele);

        return $copies >= 1 ? $this->match($interaction, $variant, $copies, GeneticMatchData::MATCHED_ON_ALLELE, $allele) : null;
    }

    private function geneMatch(GeneInteractionData $interaction, VariantData $variant): ?GeneticMatchData
    {
        if ($variant->gene === null || strcasecmp($interaction->gene, $variant->gene) !== 0) {
            return null;
        }

        return $this->match($interaction, $variant, null, GeneticMatchData::MATCHED_ON_GENE);
    }

    private function match(GeneInteractionData $interaction, VariantData $variant, ?int $copies, string $matchedOn, ?string $allele = null): GeneticMatchData
    {
        return new GeneticMatchData(
            gene: $interaction->gene,
            rsid: $variant->rsid,
            genotype: $variant->genotype,
            copies: $copies,
            relation: $interaction->relation,
            explanation: $interaction->explanation,
            sourceKeys: $interaction->sourceKeys,
            matchedOn: $matchedOn,
            allele: $allele,
            variantName: $variant->variantName,
        );
    }
}
