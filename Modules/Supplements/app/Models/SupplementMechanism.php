<?php

namespace Modules\Supplements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Supplements\Database\Factories\SupplementMechanismFactory;

class SupplementMechanism extends Model
{
    /** @use HasFactory<SupplementMechanismFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['research_id', 'description', 'source_keys'];

    protected static function newFactory(): SupplementMechanismFactory
    {
        return SupplementMechanismFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['source_keys' => 'array'];
    }

    /** @return BelongsTo<SupplementResearch, $this> */
    public function research(): BelongsTo
    {
        return $this->belongsTo(SupplementResearch::class, 'research_id');
    }
}
