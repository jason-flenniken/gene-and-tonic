<?php

namespace Modules\Genetics\Repositories;

use Modules\Genetics\Contracts\GeneticProfileReader;
use Modules\Genetics\Data\GeneticProfileData;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Data\VariantData;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

class EloquentGeneticProfileReader implements GeneticProfileReader
{
    public function forUser(int $userId): GeneticProfileData
    {
        $variants = UserVariant::query()->where('user_id', $userId)->orderBy('rsid')->get();

        $references = GeneVariant::query()
            ->whereIn('rsid', $variants->pluck('rsid'))
            ->get()
            ->keyBy('rsid');

        return new GeneticProfileData($userId, array_values($variants->map(function (UserVariant $variant) use ($references): VariantData {
            /** @var GeneVariant|null $reference */
            $reference = $references->get($variant->rsid);

            // Strand is resolved again here so a genotype stored while its row was pending matches once the alleles are known.
            $genotype = Genotype::toPlusStrand($variant->genotype, $reference?->reference_allele, $reference?->risk_allele);

            return new VariantData(
                rsid: $variant->rsid,
                gene: $reference?->gene,
                genotype: $genotype,
                referenceAllele: $reference?->reference_allele,
                riskAllele: $reference?->risk_allele,
                category: $reference?->category,
                affects: $reference?->affects,
                genotypeEffect: $reference?->genotype_effects[$genotype] ?? null,
                status: $reference->status ?? LookupStatus::Pending,
                source: $variant->source,
                group: $reference?->category_group,
            );
        })->all()));
    }
}
