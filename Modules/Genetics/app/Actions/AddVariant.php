<?php

namespace Modules\Genetics\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Genetics\Data\Genotype;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Models\GeneVariant;
use Modules\Genetics\Models\UserVariant;

class AddVariant
{
    /**
     * Records one genotype for a user, creating a pending reference row for an
     * unknown rsID. A second call for the same user and rsID overwrites the first.
     * The source label (e.g. an uploaded file's name) is cut to its 255-character column.
     *
     * @throws InvalidArgumentException on an invalid rsID, gene symbol or genotype, or a no-call
     */
    public function handle(int $userId, string $rsid, string $genotype, ?string $gene = null, string $source = 'manual', ?int $uploadId = null): GeneVariant
    {
        $rsid = strtolower(trim($rsid));

        if (preg_match('/^rs\d{1,10}$/', $rsid) !== 1) {
            throw new InvalidArgumentException('Invalid rsID.');
        }

        $gene = $gene === null || trim($gene) === '' ? null : strtoupper(trim($gene));

        if ($gene !== null && preg_match('/^[A-Z0-9-]{1,15}$/', $gene) !== 1) {
            throw new InvalidArgumentException('Invalid gene symbol.');
        }

        $source = mb_substr($source, 0, 255);

        $normalized = Genotype::normalize($genotype)
            ?? throw new InvalidArgumentException('Genotype is a no-call.');

        return DB::transaction(function () use ($userId, $rsid, $normalized, $gene, $source, $uploadId): GeneVariant {
            $reference = GeneVariant::firstOrCreate(
                ['rsid' => $rsid],
                ['gene' => $gene, 'status' => LookupStatus::Pending],
            );

            UserVariant::updateOrCreate(
                ['user_id' => $userId, 'rsid' => $rsid],
                [
                    'genotype' => Genotype::toPlusStrand($normalized, $reference->reference_allele, $reference->risk_allele),
                    'source' => $source,
                    'genetic_upload_id' => $uploadId,
                ],
            );

            return $reference;
        });
    }
}
