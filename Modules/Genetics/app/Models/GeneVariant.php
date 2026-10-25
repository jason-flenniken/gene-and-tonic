<?php

namespace Modules\Genetics\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Genetics\Database\Factories\GeneVariantFactory;
use Modules\Genetics\Enums\LookupStatus;
use Modules\Genetics\Enums\VariantGroup;

/**
 * Global, user-independent reference data for one SNP. Alleles are plus strand.
 *
 * @property int $id
 * @property string $rsid
 * @property string|null $gene
 * @property string|null $reference_allele
 * @property string|null $risk_allele
 * @property string|null $category
 * @property string|null $variant_name
 * @property VariantGroup|null $category_group
 * @property string|null $affects
 * @property array<string, string>|null $genotype_effects
 * @property list<array{url: string, title: string, domain: string}>|null $sources
 * @property LookupStatus $status
 * @property string|null $error
 * @property CarbonInterface|null $looked_up_at
 */
class GeneVariant extends Model
{
    /** @use HasFactory<GeneVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'rsid', 'gene', 'reference_allele', 'risk_allele', 'category', 'variant_name', 'category_group', 'affects',
        'genotype_effects', 'sources', 'status', 'error', 'looked_up_at',
    ];

    protected static function newFactory(): GeneVariantFactory
    {
        return GeneVariantFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'genotype_effects' => 'array',
            'sources' => 'array',
            'status' => LookupStatus::class,
            'category_group' => VariantGroup::class,
            'looked_up_at' => 'datetime',
        ];
    }
}
