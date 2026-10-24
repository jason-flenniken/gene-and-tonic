<?php

namespace Modules\Recommendations\Services;

use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Recommendations\Contracts\GeneticRelevanceReader;
use Modules\Recommendations\Data\GeneticMatchData;
use Modules\Recommendations\Data\GeneticRelevanceData;
use Modules\Supplements\Contracts\SupplementResearchReader;
use Modules\Supplements\Data\GeneInteractionData;

final class RuleBasedGeneticRelevanceReader implements GeneticRelevanceReader
{
    public function __construct(
        private GeneticProfileReader $profiles,
        private SupplementResearchReader $research,
    ) {}

    public function forUserAndSupplement(int $userId, int $supplementId): GeneticRelevanceData
    {
        $profile = $this->profiles->forUser($userId);
        $research = $this->research->forSupplement($supplementId);

        $matches = [];

        foreach ($research->geneInteractions ?? [] as $interaction) {
            array_push($matches, ...$this->matchesFor($interaction, $profile->variants));
        }

        return new GeneticRelevanceData(
            supplementId: $supplementId,
            hasProfile: ! $profile->isEmpty(),
            geneticsChecked: $research->geneticsChecked ?? false,
            matches: $matches,
        );
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

        $allele = self::singleAllele($interaction->allele);

        if ($allele === null) {
            return $this->match($interaction, $variant, null, GeneticMatchData::MATCHED_ON_RSID);
        }

        $copies = Genotype::copiesOf($variant->genotype, $allele, $variant->referenceAllele, $variant->riskAllele);

        return $copies >= 1 ? $this->match($interaction, $variant, $copies, GeneticMatchData::MATCHED_ON_ALLELE) : null;
    }

    private function geneMatch(GeneInteractionData $interaction, VariantData $variant): ?GeneticMatchData
    {
        if ($variant->gene === null || strcasecmp($interaction->gene, $variant->gene) !== 0) {
            return null;
        }

        return $this->match($interaction, $variant, null, GeneticMatchData::MATCHED_ON_GENE);
    }

    /**
     * The interaction's allele as one letter from the allele alphabet, or null
     * when research gave none or something unusable (e.g. "TT" or "C677T").
     */
    private static function singleAllele(?string $allele): ?string
    {
        $allele = strtoupper(trim((string) $allele));

        return preg_match('/^[ACGTDI]$/', $allele) === 1 ? $allele : null;
    }

    private function match(GeneInteractionData $interaction, VariantData $variant, ?int $copies, string $matchedOn): GeneticMatchData
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
        );
    }
}
