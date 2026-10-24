<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplements\Database\Factories\SupplementGeneInteractionFactory;
use Modules\Supplements\Enums\GeneRelation;

/**
 * @property string $gene
 * @property string|null $rsid
 * @property string|null $allele
 * @property GeneRelation $relation
 * @property string $explanation
 * @property array<int, string>|null $source_keys
 */
class SupplementGeneInteraction extends Model
{
    /** @use HasFactory<SupplementGeneInteractionFactory> */
    use HasFactory;

    protected $fillable = ['supplement_id', 'gene', 'rsid', 'allele', 'relation', 'explanation', 'source_keys', 'position'];

    protected static function newFactory(): SupplementGeneInteractionFactory
    {
        return SupplementGeneInteractionFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['relation' => GeneRelation::class, 'source_keys' => 'array'];
    }

    /** @return BelongsTo<Supplement, $this> */
    public function supplement(): BelongsTo
    {
        return $this->belongsTo(Supplement::class);
    }
}
